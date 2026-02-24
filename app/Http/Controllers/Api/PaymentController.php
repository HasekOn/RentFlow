<?php

namespace App\Http\Controllers\Api;

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

        if ($user->role === 'landlord') {
            $leaseIds = Lease::query()->whereIn(
                'property_id',
                $user->ownedProperties()->pluck('id')
            )->pluck('id');
            $query = Payment::query()->whereIn('lease_id', $leaseIds)
                ->with('lease.tenant');
        } else {
            $query = Payment::query()->whereIn(
                'lease_id',
                $user->leases()->pluck('id')
            )->with('lease.property');
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

        // Auto-set status
        if (!isset($validated['status'])) {
            $validated['status'] = !empty($validated['paid_date']) ? 'paid' : 'unpaid';
        }

        $payment = Payment::query()->create($validated);

        return response()->json(new PaymentResource($payment), 201);
    }

    public function show(string $id): JsonResponse
    {
        $payment = Payment::query()->with('lease.tenant', 'lease.property')->findOrFail($id);

        $this->authorize('view', $payment);

        return response()->json(new PaymentResource($payment));
    }

    public function markPaid(string $id): JsonResponse
    {
        $payment = Payment::query()->findOrFail($id);

        $this->authorize('update', $payment);

        $payment->update([
            'paid_date' => now()->toDateString(),
            'status' => 'paid',
        ]);

        // Recalculate tenant's trust score
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

        $payment->delete();

        return response()->json([
            'message' => 'Payment deleted successfully.',
        ]);
    }

    /**
     * Import payments from bank CSV
     * POST /api/payments/import-csv
     */
    public function importCsv(Request $request): JsonResponse
    {
        $this->authorize('create', Payment::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $content = file_get_contents($request->file('file')->getRealPath());

        $service = new BankImportService();
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

    protected function getDateField(): string
    {
        return 'due_date';
    }
}
