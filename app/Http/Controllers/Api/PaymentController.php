<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaseStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Events\PaymentMarkedPaid;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;
use App\Services\BankImportService;
use App\Traits\Filterable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use Filterable;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Payment::class);

        /** @var User $user */
        $user = $request->user();

        if ($user->isLandlord()) {
            $leaseIds = Lease::query()->whereIn(
                'property_id',
                $user->ownedProperties()->pluck('id')
            )->pluck('id');

            $query = Payment::query()->whereIn('lease_id', $leaseIds)
                ->with(['lease.tenant', 'lease.property']);
        } else {
            // Both manager and tenant: see only own payments
            $query = Payment::query()->whereIn(
                'lease_id',
                $user->leases()->pluck('id')
            )->with(['lease.tenant', 'lease.property']);
        }

        $this->applyFilters(
            $query,
            $request,
            filterableFields: ['status', 'type', 'lease_id'],
            sortableFields: ['due_date', 'paid_date', 'amount', 'status', 'created_at'],
        );

        return PaymentResource::collection($query->paginate(20));
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $this->authorize('create', Payment::class);

        $validated = $request->validated();

        if (! isset($validated['status'])) {
            $validated['status'] = ! empty($validated['paid_date']) ? PaymentStatus::Paid : PaymentStatus::Unpaid;
        }

        $payment = Payment::query()->create($validated);

        return response()->json(new PaymentResource($payment), 201);
    }

    public function show(string $id): JsonResponse
    {
        $payment = Payment::query()->with(['lease.tenant', 'lease.property'])->findOrFail($id);
        $this->authorize('view', $payment);

        return response()->json(new PaymentResource($payment));
    }

    public function markPaid(string $id): JsonResponse
    {
        $payment = Payment::query()->findOrFail($id);
        $this->authorize('update', $payment);

        $payment->update([
            'paid_date' => now()->toDateString(),
            'status' => PaymentStatus::Paid,
        ]);

        PaymentMarkedPaid::dispatch($payment);

        return response()->json(new PaymentResource($payment));
    }

    public function update(UpdatePaymentRequest $request, string $id): JsonResponse
    {
        $payment = Payment::query()->findOrFail($id);
        $this->authorize('update', $payment);
        $payment->update($request->validated());

        return response()->json(new PaymentResource($payment));
    }

    public function destroy(string $id): JsonResponse
    {
        $payment = Payment::query()->findOrFail($id);
        $this->authorize('delete', $payment);

        if ($payment->status === PaymentStatus::Paid) {
            return response()->json([
                'message' => 'Cannot delete a paid payment. Only unpaid or overdue payments can be deleted.',
            ], 422);
        }

        $payment->delete();

        return response()->json(['message' => 'Payment deleted successfully.']);
    }

    public function importCsv(Request $request): JsonResponse
    {
        $this->authorize('create', Payment::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $content = file_get_contents($request->file('file')->getRealPath());

        $service = new BankImportService;
        $results = $service->import($content, $request->user()->id);

        return response()->json([
            'message' => 'CSV import completed.',
            'summary' => [
                'total_rows' => $results['total_rows'],
                'matched' => count($results['matched']),
                'already_paid' => count($results['already_paid']),
                'unmatched' => count($results['unmatched']),
            ],
            'details' => $results,
        ]);
    }

    public function generateMonthly(Request $request): JsonResponse
    {
        $this->authorize('create', Payment::class);

        $user = $request->user();
        $propertyIds = $user->ownedProperties()->pluck('id');
        $activeLeases = Lease::query()->whereIn('property_id', $propertyIds)
            ->where('status', LeaseStatus::Active)
            ->get();

        $created = 0;
        $dueDate = now()->startOfMonth()->addDays(14);

        foreach ($activeLeases as $lease) {
            $existingRent = Payment::query()
                ->where('lease_id', $lease->id)
                ->where('type', PaymentType::Rent)
                ->whereYear('due_date', now()->year)
                ->whereMonth('due_date', now()->month)
                ->exists();

            if (! $existingRent) {
                Payment::create([
                    'lease_id' => $lease->id,
                    'type' => PaymentType::Rent,
                    'amount' => $lease->rent_amount,
                    'due_date' => $dueDate,
                    'variable_symbol' => $lease->variable_symbol,
                    'status' => PaymentStatus::Unpaid,
                ]);
                $created++;
            }

            if ($lease->utility_advances > 0) {
                $existingUtility = Payment::query()
                    ->where('lease_id', $lease->id)
                    ->where('type', PaymentType::Utilities)
                    ->whereYear('due_date', now()->year)
                    ->whereMonth('due_date', now()->month)
                    ->exists();

                if (! $existingUtility) {
                    Payment::create([
                        'lease_id' => $lease->id,
                        'type' => PaymentType::Utilities,
                        'amount' => $lease->utility_advances,
                        'due_date' => $dueDate,
                        'variable_symbol' => $lease->variable_symbol ? $lease->variable_symbol.'1' : null,
                        'status' => PaymentStatus::Unpaid,
                    ]);
                    $created++;
                }
            }
        }

        return response()->json([
            'message' => "Generated {$created} payments for current month.",
            'created' => $created,
        ]);
    }

    protected function getDateField(): string
    {
        return 'due_date';
    }
}
