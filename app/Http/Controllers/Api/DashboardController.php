<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Rating;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TrustScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $propertyIds = $user->ownedProperties()->pluck('id');
        $leaseIds = Lease::query()->whereIn('property_id', $propertyIds)->pluck('id');

        // Property stats
        $totalProperties = $user->ownedProperties()->count();
        $occupiedProperties = $user->ownedProperties()->where('status', 'occupied')->count();
        $availableProperties = $user->ownedProperties()->where('status', 'available')->count();
        $renovationProperties = $user->ownedProperties()->where('status', 'renovation')->count();

        // Financial stats — current month
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $monthlyIncome = Payment::query()->whereIn('lease_id', $leaseIds)
            ->where('status', 'paid')
            ->whereBetween('paid_date', [$monthStart, $monthEnd])
            ->sum('amount');

        $monthlyExpenses = Expense::query()->whereIn('property_id', $propertyIds)
            ->whereBetween('expense_date', [$monthStart, $monthEnd])
            ->sum('amount');

        // Overdue payments
        $overduePayments = Payment::query()->whereIn('lease_id', $leaseIds)
            ->where('status', 'unpaid')
            ->where('due_date', '<', now()->toDateString())
            ->count();

        // Active leases
        $activeLeases = Lease::query()->whereIn('property_id', $propertyIds)
            ->where('status', 'active')
            ->count();

        // Leases ending within 30 days
        $expiringLeases = Lease::query()->whereIn('property_id', $propertyIds)
            ->where('status', 'active')
            ->whereBetween('end_date', [now(), now()->addDays(30)])
            ->count();

        // Open tickets
        $openTickets = Ticket::query()->whereIn('property_id', $propertyIds)
            ->whereIn('status', ['new', 'in_progress'])
            ->count();

        return response()->json([
            'properties' => [
                'total' => $totalProperties,
                'occupied' => $occupiedProperties,
                'available' => $availableProperties,
                'renovation' => $renovationProperties,
            ],
            'finance' => [
                'monthly_income' => $monthlyIncome,
                'monthly_expenses' => $monthlyExpenses,
                'cashflow' => $monthlyIncome - $monthlyExpenses,
                'overdue_payments' => $overduePayments,
            ],
            'leases' => [
                'active' => $activeLeases,
                'expiring_soon' => $expiringLeases,
            ],
            'tickets' => [
                'open' => $openTickets,
            ],
        ]);
    }

    /**
     * Monthly income vs expenses for charts (last 12 months)
     * GET /api/dashboard/finance-chart
     */
    public function financeChart(Request $request): JsonResponse
    {
        $user = $request->user();
        $propertyIds = $user->ownedProperties()->pluck('id');
        $leaseIds = Lease::query()->whereIn('property_id', $propertyIds)->pluck('id');

        $months = collect();

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth()->toDateString();
            $monthEnd = $date->copy()->endOfMonth()->toDateString();

            $income = Payment::query()->whereIn('lease_id', $leaseIds)
                ->where('status', 'paid')
                ->whereBetween('paid_date', [$monthStart, $monthEnd])
                ->sum('amount');

            $expenses = Expense::query()->whereIn('property_id', $propertyIds)
                ->whereBetween('expense_date', [$monthStart, $monthEnd])
                ->sum('amount');

            $months->push([
                'month' => $date->format('Y-m'),
                'label' => $date->format('M Y'),
                'income' => round((float)$income, 2),
                'expenses' => round((float)$expenses, 2),
                'cashflow' => round((float)$income - (float)$expenses, 2),
            ]);
        }

        return response()->json($months);
    }

    /**
     * Occupancy data for pie chart
     * GET /api/dashboard/occupancy-chart
     */
    public function occupancyChart(Request $request): JsonResponse
    {
        $user = $request->user();

        $occupied = $user->ownedProperties()->where('status', 'occupied')->count();
        $available = $user->ownedProperties()->where('status', 'available')->count();
        $renovation = $user->ownedProperties()->where('status', 'renovation')->count();

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
        $tenant = User::query()->where('role', 'tenant')->findOrFail($id);

        $service = new TrustScoreService();
        $score = $service->calculate($tenant);

        $leaseIds = $tenant->leases()->pluck('id');

        $totalPayments = Payment::query()->whereIn('lease_id', $leaseIds)
            ->where('type', 'rent')
            ->count();

        $onTimePayments = Payment::query()->whereIn('lease_id', $leaseIds)
            ->where('type', 'rent')
            ->where('status', 'paid')
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
                    ? round($onTimePayments / $totalPayments * 100, 1) . '%'
                    : 'N/A',
                'average_rating' => $averageRating ? round((float)$averageRating, 1) . '/5' : 'No ratings yet',
            ],
        ]);
    }

    /**
     * Mark notification as read
     * PUT /api/notifications/{id}/read
     */
    public function markNotificationRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

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
