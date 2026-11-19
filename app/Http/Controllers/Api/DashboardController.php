<?php

namespace App\Http\Controllers\Api;

use App\Actions\Users\MarkNotificationRead;
use App\Enums\LeaseStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PropertyStatus;
use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Rating;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TrustScoreService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isLandlord()) {
            $propertyIds = $user->ownedProperties()->pluck('id');
        } elseif ($user->isManager()) {
            $propertyIds = $user->managedProperties()->pluck('properties.id');
        } else {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Property stats — use propertyIds for manager compatibility
        $totalProperties = $user->isLandlord()
            ? $user->ownedProperties()->count()
            : $user->managedProperties()->count();

        $occupiedProperties = $user->isLandlord()
            ? $user->ownedProperties()->where('status', PropertyStatus::Occupied)->count()
            : $user->managedProperties()->where('properties.status', PropertyStatus::Occupied)->count();

        $availableProperties = $user->isLandlord()
            ? $user->ownedProperties()->where('status', PropertyStatus::Available)->count()
            : $user->managedProperties()->where('properties.status', PropertyStatus::Available)->count();

        $renovationProperties = $user->isLandlord()
            ? $user->ownedProperties()->where('status', PropertyStatus::Renovation)->count()
            : $user->managedProperties()->where('properties.status', PropertyStatus::Renovation)->count();

        // Active leases
        $activeLeases = Lease::query()->whereIn('property_id', $propertyIds)
            ->where('status', LeaseStatus::Active)
            ->count();

        // Leases ending within 30 days
        $expiringLeases = Lease::query()->whereIn('property_id', $propertyIds)
            ->where('status', LeaseStatus::Active)
            ->where('end_date', '>=', today())
            ->where('end_date', '<', today()->addDays(31))
            ->count();

        // Open tickets
        $openTickets = Ticket::query()->whereIn('property_id', $propertyIds)
            ->whereIn('status', [TicketStatus::New, TicketStatus::InProgress])
            ->count();

        $stats = [
            'properties' => [
                'total' => $totalProperties,
                'occupied' => $occupiedProperties,
                'available' => $availableProperties,
                'renovation' => $renovationProperties,
            ],
            'leases' => [
                'active' => $activeLeases,
                'expiring_soon' => $expiringLeases,
            ],
            'tickets' => [
                'open' => $openTickets,
            ],
        ];

        // celkové finance jen pronajímateli; správce dostane přehled plateb, které potvrzuje (#17)
        if ($user->isLandlord()) {
            $stats['finance'] = $this->monthFinance($propertyIds);
        } else {
            $stats['payments'] = $this->paymentOverview($propertyIds);
        }

        return response()->json($stats);
    }

    /**
     * Nezaplacené a po splatnosti platby aktivních smluv daných nemovitostí (stejný výřez jako Payment::visibleTo
     * pro správce): počet a součet v DB.
     *
     * @param  Collection<int, int>  $propertyIds
     * @return array<string, array{count: int, amount: float}>
     */
    private function paymentOverview(Collection $propertyIds): array
    {
        $overview = [];
        foreach (['unpaid' => PaymentStatus::Unpaid, 'overdue' => PaymentStatus::Overdue] as $key => $status) {
            $payments = Payment::query()
                ->where('status', $status)
                ->whereHas('lease', fn ($lease) => $lease
                    ->where('status', LeaseStatus::Active)
                    ->whereIn('property_id', $propertyIds));
            $overview[$key] = [
                'count' => (clone $payments)->count(),
                'amount' => Money::toFloat(Money::fromSum($payments->sum('amount'))),
            ];
        }

        return $overview;
    }

    /**
     * Příjmy, výdaje a platby po splatnosti za aktuální měsíc.
     *
     * @param  Collection<int, int>  $propertyIds
     * @return array<string, mixed>
     */
    private function monthFinance(Collection $propertyIds): array
    {
        $leaseIds = Lease::query()->whereIn('property_id', $propertyIds)->pluck('id');
        // rozsah >= začátek a < začátek dalšího měsíce (#28): započítá se i poslední den měsíce
        $monthStart = today()->startOfMonth();
        $nextMonth = $monthStart->copy()->addMonth();

        // součty v DB, rozdíl v haléřích (#49)
        $monthlyIncome = Money::fromSum(Payment::query()->whereIn('lease_id', $leaseIds)
            ->where('status', PaymentStatus::Paid)
            ->where('paid_date', '>=', $monthStart)
            ->where('paid_date', '<', $nextMonth)
            ->sum('amount'));

        $monthlyExpenses = Money::fromSum(Expense::query()->whereIn('property_id', $propertyIds)
            ->where('expense_date', '>=', $monthStart)
            ->where('expense_date', '<', $nextMonth)
            ->sum('amount'));

        // Overdue payments — only those already marked overdue by scheduler
        $overduePayments = Payment::query()->whereIn('lease_id', $leaseIds)
            ->where('status', PaymentStatus::Overdue)
            ->count();

        return [
            'monthly_income' => Money::toFloat($monthlyIncome),
            'monthly_expenses' => Money::toFloat($monthlyExpenses),
            'cashflow' => Money::toFloat($monthlyIncome - $monthlyExpenses),
            'overdue_payments' => $overduePayments,
        ];
    }

    /**
     * Monthly income vs expenses for charts (last 12 months)
     * GET /api/dashboard/finance-chart
     */
    public function financeChart(Request $request): JsonResponse
    {
        $user = $request->user();

        // finance jen pronajímateli, správce ani nájemník je nevidí (#17)
        if (! $user->isLandlord()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $propertyIds = $user->ownedProperties()->pluck('id');

        $leaseIds = Lease::query()->whereIn('property_id', $propertyIds)->pluck('id');

        // 12 měsíců od začátku měsíce (31. ledna minus měsíc nesmí přetéct do března), rozsah >= a < (#28)
        $from = today()->startOfMonth()->subMonths(11);
        $to = today()->startOfMonth()->addMonth();

        // dva dotazy místo 24 (#48): SUM po dnech (bez SQL funkcí na měsíc, DB-agnosticky), do měsíců v PHP v haléřích
        $income = $this->sumByMonth(Payment::query()->whereIn('lease_id', $leaseIds)
            ->where('status', PaymentStatus::Paid), 'paid_date', $from, $to);
        $expenses = $this->sumByMonth(Expense::query()->whereIn('property_id', $propertyIds), 'expense_date', $from, $to);

        $months = collect();
        for ($date = $from->copy(); $date < $to; $date->addMonth()) {
            $month = $date->format('Y-m');
            $monthIncome = $income[$month] ?? 0;
            $monthExpenses = $expenses[$month] ?? 0;

            $months->push([
                'month' => $month,
                'label' => $date->format('M Y'),
                'income' => Money::toFloat($monthIncome),
                'expenses' => Money::toFloat($monthExpenses),
                'cashflow' => Money::toFloat($monthIncome - $monthExpenses),
            ]);
        }

        return response()->json($months);
    }

    /**
     * Součty částek po měsících (`Y-m` => haléře) v rozsahu dat [$from, $to).
     *
     * @param  Builder<Payment>|Builder<Expense>  $query
     * @return array<string, int>
     */
    private function sumByMonth(Builder $query, string $dateColumn, Carbon $from, Carbon $to): array
    {
        $months = [];
        $days = $query
            ->where($dateColumn, '>=', $from)
            ->where($dateColumn, '<', $to)
            ->groupBy($dateColumn)
            ->toBase()
            ->get([$dateColumn.' as day', DB::raw('SUM(amount) as total')]);

        foreach ($days as $day) {
            $month = Carbon::parse($day->day)->format('Y-m');
            $months[$month] = ($months[$month] ?? 0) + Money::fromSum($day->total);
        }

        return $months;
    }

    /**
     * Occupancy data for pie chart
     * GET /api/dashboard/occupancy-chart
     */
    public function occupancyChart(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isLandlord()) {
            $query = $user->ownedProperties();
        } elseif ($user->isManager()) {
            $query = $user->managedProperties();
        } else {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $occupied = (clone $query)->where('status', PropertyStatus::Occupied)->count();
        $available = (clone $query)->where('status', PropertyStatus::Available)->count();
        $renovation = (clone $query)->where('status', PropertyStatus::Renovation)->count();

        return response()->json([
            ['label' => 'Occupied', 'value' => $occupied],
            ['label' => 'Available', 'value' => $available],
            ['label' => 'Renovation', 'value' => $renovation],
        ]);
    }

    /**
     * Get trust score detail for a tenant
     * GET /api/tenants/{id}/trust-score
     */
    public function trustScore(Request $request, string $id): JsonResponse
    {
        $tenant = User::query()->whereIn('role', [Role::Tenant, Role::Manager])->find($id);
        if ($tenant === null) {
            abort(404);
        }
        $this->authorize('view', $tenant);

        $service = new TrustScoreService;
        $score = $service->calculate($tenant);

        $leaseIds = $tenant->leases()->pluck('id');

        $totalPayments = Payment::query()->whereIn('lease_id', $leaseIds)
            ->where('type', PaymentType::Rent)
            ->count();

        $onTimePayments = Payment::query()->whereIn('lease_id', $leaseIds)
            ->where('type', PaymentType::Rent)
            ->where('status', PaymentStatus::Paid)
            ->whereColumn('paid_date', '<=', 'due_date')
            ->count();

        $averageRating = Rating::query()->whereIn('lease_id', $leaseIds)->avg('score');

        return response()->json([
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'trust_score' => $score,
            'breakdown' => [
                'total_payments' => $totalPayments,
                'on_time_payments' => $onTimePayments,
                'payment_ratio' => $totalPayments > 0
                    ? round($onTimePayments / $totalPayments * 100, 1).'%'
                    : 'N/A',
                'average_rating' => $averageRating ? round((float) $averageRating, 1).'/5' : 'No ratings yet',
            ],
        ]);
    }

    /**
     * Mark notification as read
     * PUT /api/notifications/{id}/read
     */
    public function markNotificationRead(Request $request, string $id, MarkNotificationRead $markRead): JsonResponse
    {
        $notification = $request->user()->notifications()->find($id);
        if ($notification === null) {
            abort(404);
        }

        $markRead->handle($notification);

        return response()->json([
            'message' => 'Notification marked as read.',
        ]);
    }

    /**
     * Get notifications for the logged-in user
     * GET /api/notifications
     */
    public function notifications(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->take(20)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->data['type'] ?? 'unknown',
                    'data' => $notification->data,
                    'read' => $notification->read_at !== null,
                    'created_at' => $notification->created_at->toDateTimeString(),
                ];
            });

        return response()->json($notifications);
    }
}
