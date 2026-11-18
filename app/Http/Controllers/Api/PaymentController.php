<?php

namespace App\Http\Controllers\Api;

use App\Actions\Payments\GenerateMonthlyPayments;
use App\Enums\PaymentStatus;
use App\Events\PaymentMarkedPaid;
use App\Http\Controllers\Api\Concerns\FindsLease;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\BankImportService;
use App\Traits\Filterable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use Filterable, FindsLease;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Payment::class);

        $query = Payment::query()
            ->visibleTo($request->user())
            ->with(['lease.tenant', 'lease.property']);

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
        $lease = $this->findLease((string) $request->validated('lease_id'));
        $this->authorize('create', [Payment::class, $lease]);

        $validated = $request->validated();

        if (! isset($validated['status'])) {
            $validated['status'] = ! empty($validated['paid_date']) ? PaymentStatus::Paid : PaymentStatus::Unpaid;
        }

        $payment = Payment::query()->create($validated);

        return response()->json(new PaymentResource($payment), 201);
    }

    public function show(string $id): JsonResponse
    {
        $payment = Payment::query()->with(['lease.tenant', 'lease.property'])->find($id);
        if ($payment === null) {
            abort(404);
        }
        $this->authorize('view', $payment);

        return response()->json(new PaymentResource($payment));
    }

    public function markPaid(string $id): JsonResponse
    {
        $payment = $this->findPayment($id);
        $this->authorize('markPaid', $payment);

        $payment->update([
            'paid_date' => now()->toDateString(),
            'status' => PaymentStatus::Paid,
        ]);

        PaymentMarkedPaid::dispatch($payment);

        return response()->json(new PaymentResource($payment));
    }

    public function update(UpdatePaymentRequest $request, string $id): JsonResponse
    {
        $payment = $this->findPayment($id);
        $this->authorize('update', $payment);
        $payment->update($request->validated());

        return response()->json(new PaymentResource($payment));
    }

    public function destroy(string $id): JsonResponse
    {
        $payment = $this->findPayment($id);
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
        $this->authorize('import', Payment::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $content = file_get_contents($request->file('file')->getRealPath());

        // páruje jen smlouvy nemovitostí přihlášeného pronajímatele
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

    public function generateMonthly(Request $request, GenerateMonthlyPayments $generate): JsonResponse
    {
        $this->authorize('generate', Payment::class);

        $created = $generate->handle($request->user(), now());

        return response()->json([
            'message' => "Generated {$created} payments for current month.",
            'created' => $created,
        ]);
    }

    private function findPayment(string $id): Payment
    {
        $payment = Payment::query()->with('lease.property')->find($id);
        if ($payment === null) {
            abort(404);
        }

        return $payment;
    }

    protected function getDateField(): string
    {
        return 'due_date';
    }
}
