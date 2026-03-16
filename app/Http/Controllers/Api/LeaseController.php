<?php

namespace App\Http\Controllers\Api;

use App\Events\LeaseCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaseRequest;
use App\Http\Requests\UpdateLeaseRequest;
use App\Http\Resources\LeaseResource;
use App\Models\Lease;
use App\Models\Property;
use App\Models\User;
use App\Traits\Filterable;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LeaseController extends Controller
{
    use Filterable;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Lease::class);

        /** @var User $user */
        $user = $request->user();

        if ($user->role === 'landlord') {
            $baseQuery = $request->filled('tenant_id')
                ? Lease::withTrashed()
                : Lease::query();

            $query = $baseQuery->whereIn(
                'property_id',
                $user->ownedProperties()->pluck('id')
            )->with(['property.images', 'tenant']);
        } else {
            // Both manager and tenant: see only own leases (as tenant)
            $query = Lease::withTrashed()
                ->where('tenant_id', $user->id)
                ->with(['property.images', 'tenant']);
        }

        $this->applyFilters(
            $query,
            $request,
            filterableFields: ['status', 'property_id', 'tenant_id'],
            sortableFields: ['start_date', 'end_date', 'rent_amount', 'status', 'created_at'],
        );

        return LeaseResource::collection($query->paginate(15));
    }

    public function store(StoreLeaseRequest $request): JsonResponse
    {
        $this->authorize('create', Lease::class);

        $property = Property::query()->findOrFail($request->validated('property_id'));

        if ($property->landlord_id !== $request->user()->id) {
            return response()->json(['message' => 'You can only create leases for your own properties.'], 403);
        }

        if ($property->status === 'renovation') {
            return response()->json(['message' => 'Cannot create lease for a property under renovation.'], 422);
        }

        $existingActive = Lease::query()
            ->where('property_id', $request->validated('property_id'))
            ->where('tenant_id', $request->validated('tenant_id'))
            ->where('status', 'active')
            ->exists();

        if ($existingActive) {
            return response()->json(['message' => 'An active lease already exists for this tenant on this property.'], 422);
        }

        $lease = Lease::query()->create($request->validated());
        $lease->load(['property', 'tenant']);

        LeaseCreated::dispatch($lease);

        if ($property->status === 'available') {
            $property->update(['status' => 'occupied']);
        }

        return response()->json(new LeaseResource($lease), 201);
    }

    public function update(UpdateLeaseRequest $request, string $id): JsonResponse
    {
        $lease = Lease::query()->findOrFail($id);
        $this->authorize('update', $lease);

        $oldStatus = $lease->status;
        $lease->update($request->validated());

        $newStatus = $lease->status;
        if ($oldStatus === 'active' && in_array($newStatus, ['ended', 'terminated'])) {
            $property = $lease->property;
            if ($property) {
                $hasOtherActive = $property->leases()
                    ->where('id', '!=', $lease->id)
                    ->where('status', 'active')
                    ->exists();
                if (! $hasOtherActive) {
                    $property->update(['status' => 'available']);
                }
            }
        }

        return response()->json(new LeaseResource($lease));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $lease = Lease::withTrashed()->with(['property', 'tenant', 'payments', 'ratings'])->findOrFail($id);
        $this->authorize('view', $lease);

        return response()->json(new LeaseResource($lease));
    }

    public function destroy(string $id): JsonResponse
    {
        $lease = Lease::query()->findOrFail($id);
        $this->authorize('delete', $lease);

        $property = $lease->property;
        $lease->delete();

        if ($property && $property->status === 'occupied') {
            $hasOtherActive = $property->leases()
                ->where('id', '!=', $lease->id)
                ->where('status', 'active')
                ->exists();
            if (! $hasOtherActive) {
                $property->update(['status' => 'available']);
            }
        }

        return response()->json(['message' => 'Lease deleted successfully.']);
    }

    public function generatePdf(Request $request, string $id)
    {
        $lease = Lease::withTrashed()->with(['property', 'tenant'])->findOrFail($id);
        $this->authorize('view', $lease);

        $property = $lease->property;
        $tenant = $lease->tenant;
        $landlord = $property->landlord;

        $pdf = Pdf::loadView('pdf.lease-contract', compact('lease', 'property', 'tenant', 'landlord'));
        $pdf->setPaper('A4');

        $filename = 'contract_lease_'.$lease->id.'.pdf';
        $path = 'contracts/'.$filename;

        Storage::disk('public')->put($path, $pdf->output());
        $lease->update(['contract_path' => $path]);

        return $pdf->download($filename);
    }

    protected function getDateField(): string
    {
        return 'start_date';
    }
}
