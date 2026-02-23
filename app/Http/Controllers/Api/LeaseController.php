<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaseRequest;
use App\Http\Requests\UpdateLeaseRequest;
use App\Models\Lease;
use App\Models\Property;
use App\Notifications\TenantInvitationNotification;
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
            $leases = Lease::query()->whereIn(
                'property_id',
                $user->ownedProperties()->pluck('id')
            )->with(['property', 'tenant'])->get();
        } else {
            $leases = $user->leases()->with('property')->get();
        }

        return response()->json($leases);
    }

    public function store(StoreLeaseRequest $request): JsonResponse
    {
        $this->authorize('create', Lease::class);

        // Verify the property belongs to the logged-in landlord
        $property = Property::query()->findOrFail($request->validated('property_id'));

        if ($property->landlord_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You can only create leases for your own properties.',
            ], 403);
        }

        $lease = Lease::query()->create($request->validated());
        $lease->load(['property', 'tenant']);

        // Send invitation to tenant
        $lease->tenant->notify(new TenantInvitationNotification($lease));

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
        $lease = Lease::query()->findOrFail($id);

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

    public function update(UpdateLeaseRequest $request, string $id): JsonResponse
    {
        $lease = Lease::query()->findOrFail($id);
        
        $this->authorize('update', $lease);

        $lease->update($request->validated());

        return response()->json($lease);
    }
}
