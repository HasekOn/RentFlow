<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $user = $request->user();

        if ($user->role === 'landlord') {
            $leaseIds = Lease::whereIn(
                'property_id',
                $user->ownedProperties()->pluck('id')
            )->pluck('id');

            $payments = Payment::whereIn('lease_id', $leaseIds)
                ->with('lease.tenant')
                ->get();
        } else {
            $payments = Payment::whereIn(
                'lease_id',
                $user->leases()->pluck('id')
            )->with('lease.property')->get();
        }

        return response()->json($payments);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Payment::class);

        $validated = $request->validate([
            'lease_id' => ['required', 'exists:leases,id'],
            'type' => ['required', 'in:rent,utilities,deposit,other'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['required', 'date'],
            'paid_date' => ['nullable', 'date'],
            'variable_symbol' => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'in:paid,unpaid,overdue'],
            'note' => ['nullable', 'string'],
        ]);

        // Auto-set status based on paid_date
        if (!empty($validated['paid_date']) && !isset($validated['status'])) {
            $validated['status'] = 'paid';
        }

        $payment = Payment::create($validated);

        return response()->json($payment, 201);
    }

    public function show(string $id): JsonResponse
    {
        $payment = Payment::with('lease.tenant', 'lease.property')->findOrFail($id);

        $this->authorize('view', $payment);

        return response()->json($payment);
    }

    public function markPaid(string $id): JsonResponse
    {
        $payment = Payment::findOrFail($id);

        $this->authorize('update', $payment);

        $payment->update([
            'paid_date' => now()->toDateString(),
            'status' => 'paid',
        ]);

        return response()->json($payment);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $payment = Payment::findOrFail($id);

        $this->authorize('update', $payment);

        $validated = $request->validate([
            'type' => ['sometimes', 'in:rent,utilities,deposit,other'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'due_date' => ['sometimes', 'date'],
            'paid_date' => ['nullable', 'date'],
            'variable_symbol' => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'in:paid,unpaid,overdue'],
            'note' => ['nullable', 'string'],
        ]);

        $payment->update($validated);

        return response()->json($payment);
    }

    public function destroy(string $id): JsonResponse
    {
        $payment = Payment::findOrFail($id);

        $this->authorize('delete', $payment);
        
        $payment->delete();

        return response()->json([
            'message' => 'Payment deleted successfully.',
        ]);
    }
}
