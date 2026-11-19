<?php

namespace App\Http\Controllers\Api;

use App\Actions\Leases\CreateLease;
use App\Actions\Leases\DeleteLease;
use App\Actions\Leases\UpdateLease;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    public function store(StoreLeaseRequest $request, CreateLease $create): JsonResponse
    {
        $property = $this->findProperty((string) $request->validated('property_id'));
        $this->authorize('create', [Lease::class, $property]);

        $lease = $create->handle($property, $request->validated());
        $lease->load('tenant');

        return response()->json(new LeaseResource($lease), 201);
    }

    public function update(UpdateLeaseRequest $request, string $id, UpdateLease $update): JsonResponse
    {
        $lease = $this->findLease($id);
        $this->authorize('update', $lease);

        // kontroly období a stavu až po autorizaci, hláška obsahuje datum smlouvy
        $update->handle($lease, $request->validated());

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

    public function destroy(string $id, DeleteLease $delete): JsonResponse
    {
        $lease = $this->findLease($id);
        $this->authorize('delete', $lease);

        $delete->handle($lease);

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
}
