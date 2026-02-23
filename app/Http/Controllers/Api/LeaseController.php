<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Property;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LeaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Lease::class);

        $user = $request->user();

        if ($user->role === 'landlord') {
            $leases = Lease::whereIn(
                'property_id',
                $user->ownedProperties()->pluck('id')
            )->with(['property', 'tenant'])->get();
        } else {
            $leases = $user->leases()->with('property')->get();
        }

        return response()->json($leases);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Lease::class);

        $validated = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'tenant_id' => ['required', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'rent_amount' => ['required', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'utility_advances' => ['nullable', 'numeric', 'min:0'],
            'variable_symbol' => ['nullable', 'string', 'max:20', 'unique:leases'],
        ]);

        $property = Property::findOrFail($validated['property_id']);

        if ($property->landlord_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You can only create leases for your own properties.',
            ], 403);
        }

        $lease = Lease::create($validated);

        $lease->load(['property', 'tenant']);

        return response()->json($lease, 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $lease = Lease::with([
            'property',
            'tenant',
            'payments',
            'ratings',
        ])->findOrFail($id);

        $this->authorize('view', $lease);

        return response()->json($lease);
    }

    public function destroy(string $id): JsonResponse
    {
        $lease = Lease::findOrFail($id);

        $this->authorize('delete', $lease);

        $lease->delete();

        return response()->json([
            'message' => 'Lease deleted successfully.',
        ]);
    }

    /**
     * Generate PDF contract for a lease
     * GET /api/leases/{id}/generate-pdf
     */
    public function generatePdf(Request $request, string $id)
    {
        $lease = Lease::with(['property', 'tenant'])->findOrFail($id);
        $this->authorize('view', $lease);

        $property = $lease->property;
        $tenant = $lease->tenant;
        $landlord = $property->landlord;

        $pdf = Pdf::loadView('pdf.lease-contract', compact(
            'lease',
            'property',
            'tenant',
            'landlord'
        ));

        $pdf->setPaper('A4', 'portrait');

        $filename = 'contract_lease_' . $lease->id . '.pdf';
        $path = 'contracts/' . $filename;

        Storage::disk('public')->put(
            $path,
            $pdf->output()
        );

        $lease->update(['contract_path' => $path]);

        return $pdf->download($filename);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $lease = Lease::findOrFail($id);

        $this->authorize('update', $lease);

        $validated = $request->validate([
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'rent_amount' => ['sometimes', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'utility_advances' => ['nullable', 'numeric', 'min:0'],
            'variable_symbol' => ['nullable', 'string', 'max:20', 'unique:leases,variable_symbol,' . $lease->id],
            'status' => ['sometimes', 'in:active,ended,terminated'],
        ]);

        $lease->update($validated);

        return response()->json($lease);
    }
}
