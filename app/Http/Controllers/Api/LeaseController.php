<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaseStatus;
use App\Enums\PropertyStatus;
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
use Illuminate\Http\Response;

class LeaseController extends Controller
{
    use Filterable;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Lease::class);

        /** @var User $user */
        $user = $request->user();

        if ($user->isLandlord()) {
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

        if ($property->status === PropertyStatus::Renovation) {
            return response()->json(['message' => 'Cannot create lease for a property under renovation.'], 422);
        }

        $existingActive = Lease::query()
            ->where('property_id', $request->validated('property_id'))
            ->where('tenant_id', $request->validated('tenant_id'))
            ->where('status', LeaseStatus::Active)
            ->exists();

        if ($existingActive) {
            return response()->json(['message' => 'An active lease already exists for this tenant on this property.'], 422);
        }

        $lease = Lease::query()->create($request->validated());
        $lease->load(['property', 'tenant']);

        LeaseCreated::dispatch($lease);

        if ($property->status === PropertyStatus::Available) {
            $property->update(['status' => PropertyStatus::Occupied]);
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
        if ($oldStatus === LeaseStatus::Active && in_array($newStatus, [LeaseStatus::Ended, LeaseStatus::Terminated], true)) {
            $property = $lease->property;
            if ($property) {
                $hasOtherActive = $property->leases()
                    ->where('id', '!=', $lease->id)
                    ->where('status', LeaseStatus::Active)
                    ->exists();
                if (! $hasOtherActive) {
                    $property->update(['status' => PropertyStatus::Available]);
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

        if ($property && $property->status === PropertyStatus::Occupied) {
            $hasOtherActive = $property->leases()
                ->where('id', '!=', $lease->id)
                ->where('status', LeaseStatus::Active)
                ->exists();
            if (! $hasOtherActive) {
                $property->update(['status' => PropertyStatus::Available]);
            }
        }

        return response()->json(['message' => 'Lease deleted successfully.']);
    }

    /**
     * PDF se generuje za běhu a nikam se neukládá (osobní údaje, GET bez vedlejšího efektu).
     */
    public function generatePdf(string $id): Response
    {
        $lease = Lease::withTrashed()->with(['property', 'tenant'])->findOrFail($id);
        $this->authorize('view', $lease);

        $property = $lease->property;
        $tenant = $lease->tenant;
        $landlord = $property->landlord;

        $pdf = Pdf::loadView('pdf.lease-contract', compact('lease', 'property', 'tenant', 'landlord'));
        $pdf->setPaper('A4');

        return $pdf->download('contract_lease_'.$lease->id.'.pdf');
    }

    protected function getDateField(): string
    {
        return 'start_date';
    }
}
