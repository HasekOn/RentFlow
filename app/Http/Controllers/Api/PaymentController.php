<?php

namespace App\Http\Controllers\Api;

use App\Actions\Payments\CreatePayment;
use App\Actions\Payments\DeletePayment;
use App\Actions\Payments\GenerateMonthlyPayments;
use App\Actions\Payments\ImportBankPayments;
use App\Actions\Payments\MarkPaymentPaid;
use App\Actions\Payments\UpdatePayment;
use App\Http\Controllers\Api\Concerns\FindsLease;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Traits\Filterable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    use Filterable, FindsLease;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Payment::class);

        $query = Payment::query()
            ->visibleTo($this->currentUser($request))
            ->with(['lease.tenant', 'lease.property']);

        $this->applyFilters(
            $query,
            $request,
            filterableFields: ['status', 'type', 'lease_id'],
            sortableFields: ['due_date', 'paid_date', 'amount', 'status', 'created_at'],
        );

        return PaymentResource::collection($query->paginate(20));
    }

    public function store(StorePaymentRequest $request, CreatePayment $create): JsonResponse
    {
        $lease = $this->findLease((string) $request->validated('lease_id'));
        $this->authorize('create', [Payment::class, $lease]);

        $payment = $create->handle($lease, $request->validated());

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

    public function markPaid(string $id, MarkPaymentPaid $markPaid): JsonResponse
    {
        $payment = $this->findPayment($id);
        $this->authorize('markPaid', $payment);

        $markPaid->handle($payment, today());

        return response()->json(new PaymentResource($payment));
    }

    public function update(UpdatePaymentRequest $request, string $id, UpdatePayment $update): JsonResponse
    {
        $payment = $this->findPayment($id);
        $this->authorize('update', $payment);

        $update->handle($payment, $request->validated());

        return response()->json(new PaymentResource($payment));
    }

    public function destroy(string $id, DeletePayment $delete): JsonResponse
    {
        $payment = $this->findPayment($id);
        $this->authorize('delete', $payment);

        $delete->handle($payment);

        return response()->json(['message' => 'Payment deleted successfully.']);
    }

    public function importCsv(Request $request, ImportBankPayments $import): JsonResponse
    {
        $this->authorize('import', Payment::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        // páruje jen smlouvy nemovitostí přihlášeného pronajímatele
        $results = $import->handle($this->currentUser($request), (string) $request->file('file')->get());

        return response()->json([
            'message' => 'CSV import completed.',
            'summary' => [
                'total_rows' => $results['total_rows'],
                'matched' => count($results['matched']),
                'to_review' => count($results['to_review']),
                'already_paid' => count($results['already_paid']),
                'unmatched' => count($results['unmatched']),
            ],
            'details' => $results,
        ]);
    }

    public function generateMonthly(Request $request, GenerateMonthlyPayments $generate): JsonResponse
    {
        $this->authorize('generate', Payment::class);

        $created = $generate->handle($this->currentUser($request), now());

        return response()->json([
            'message' => "Generated {$created} payments for current month.",
            'created' => $created,
        ]);
    }

    private function findPayment(string $id): Payment
    {
        $payment = Payment::query()->with('lease.property', 'lease.tenant')->find($id);
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
