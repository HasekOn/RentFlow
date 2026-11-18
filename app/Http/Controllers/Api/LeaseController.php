<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaseStatus;
use App\Enums\PropertyStatus;
use App\Events\LeaseCreated;
use App\Http\Controllers\Api\Concerns\FindsLease;
use App\Http\Controllers\Api\Concerns\FindsProperty;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaseRequest;
use App\Http\Requests\UpdateLeaseRequest;
use App\Http\Resources\LeaseResource;
use App\Models\Lease;
use App\Models\User;
use App\Traits\Filterable;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class LeaseController extends Controller
{
    use Filterable, FindsLease, FindsProperty;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Lease::class);

        /** @var User $user */
        $user = $request->user();

        // smazané smlouvy jen v historii konkrétní osoby (filtr tenant_id)
        $query = ($this->stringInput($request, 'tenant_id') !== null ? Lease::withTrashed() : Lease::query())
            ->visibleTo($user)
            ->with(['property.images', 'tenant']);

        // nájemné vidí každý u všech smluv, které mu visibleTo vrátí (i přiřazený správce), řadit podle něj smí všichni
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
        $property = $this->findProperty((string) $request->validated('property_id'));
        $this->authorize('create', [Lease::class, $property]);

        if ($property->status === PropertyStatus::Renovation) {
            return response()->json(['message' => 'Cannot create lease for a property under renovation.'], 422);
        }

        $existingActive = Lease::query()
            ->where('property_id', $property->id)
            ->where('tenant_id', $request->validated('tenant_id'))
            ->where('status', LeaseStatus::Active)
            ->exists();

        if ($existingActive) {
            return response()->json(['message' => 'An active lease already exists for this tenant on this property.'], 422);
        }

        $start = Carbon::parse($request->validated('start_date'));
        $end = $request->validated('end_date') !== null ? Carbon::parse($request->validated('end_date')) : null;
        if ($this->overlapsActiveLease($property->id, $start, $end)) {
            return $this->overlapResponse();
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
        $lease = $this->findLease($id);
        $this->authorize('update', $lease);

        $oldStatus = $lease->status;
        $lease->fill($request->validated());

        // konec po začátku i proti uloženému start_date (#30); až po autorizaci, hláška obsahuje datum smlouvy
        if ($lease->isDirty(['start_date', 'end_date'])) {
            Validator::make(
                ['end_date' => $lease->end_date?->toDateString()],
                ['end_date' => ['nullable', 'after:'.$lease->start_date->toDateString()]],
            )->validate();
        }

        // překryv hlídat při změně období a při reaktivaci (#22)
        if ($lease->status === LeaseStatus::Active
            && ($oldStatus !== LeaseStatus::Active || $lease->isDirty(['start_date', 'end_date']))
            && $this->overlapsActiveLease($lease->property_id, $lease->start_date, $lease->end_date, $lease->id)) {
            return $this->overlapResponse();
        }

        $lease->save();

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

    public function show(string $id): JsonResponse
    {
        $lease = Lease::withTrashed()->with(['property', 'tenant', 'payments', 'ratings'])->find($id);
        if ($lease === null) {
            abort(404);
        }
        $this->authorize('view', $lease);

        return response()->json(new LeaseResource($lease));
    }

    public function destroy(string $id): JsonResponse
    {
        $lease = $this->findLease($id);
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
     * Obsahuje kauci a osobní údaje, proto jen vlastník a nájemník (LeasePolicy::viewFinance), správce ne.
     */
    public function generatePdf(string $id): Response
    {
        $lease = Lease::withTrashed()->with(['property.landlord', 'tenant'])->find($id);
        if ($lease === null) {
            abort(404);
        }
        $this->authorize('viewFinance', $lease);

        $property = $lease->property;
        $tenant = $lease->tenant;
        $landlord = $property?->landlord;

        $pdf = Pdf::loadView('pdf.lease-contract', compact('lease', 'property', 'tenant', 'landlord'));
        $pdf->setPaper('A4');

        return $pdf->download('contract_lease_'.$lease->id.'.pdf');
    }

    protected function getDateField(): string
    {
        return 'start_date';
    }

    /**
     * Na jedné nemovitosti nesmí být aktivní smlouvy s překrývajícím se obdobím (rozhodnutí z auditu, #22).
     */
    private function overlapsActiveLease(int $propertyId, CarbonInterface $start, ?CarbonInterface $end, ?int $ignoreId = null): bool
    {
        return Lease::query()
            ->where('property_id', $propertyId)
            ->where('status', LeaseStatus::Active)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->overlapping($start, $end)
            ->exists();
    }

    private function overlapResponse(): JsonResponse
    {
        return response()->json(['message' => 'Another active lease on this property overlaps the given period.'], 422);
    }
}
