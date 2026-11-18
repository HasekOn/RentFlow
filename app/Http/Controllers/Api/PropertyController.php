<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaseStatus;
use App\Http\Controllers\Api\Concerns\FindsProperty;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use App\Models\User;
use App\Traits\Filterable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    use Filterable, FindsProperty;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Property::class);

        /** @var User $user */
        $user = $request->user();

        $query = Property::query()
            ->visibleTo($user)
            ->with(['leases' => $this->visibleLeases($user), 'images']);

        // cena je finance vlastníka: řadit podle ní jde, jen když seznam nemá cizí nemovitosti, jinak se sort ignoruje
        $sortableFields = ['address', 'city', 'size', 'status', 'created_at'];
        if (ltrim((string) $this->stringInput($request, 'sort'), '-') === 'purchase_price'
            && ! Property::query()->visibleTo($user)->where('landlord_id', '!=', $user->id)->exists()) {
            $sortableFields[] = 'purchase_price';
        }

        $this->applyFilters(
            $query,
            $request,
            filterableFields: ['status', 'city', 'disposition'],
            sortableFields: $sortableFields,
            searchableFields: ['address', 'city', 'description'],
        );

        return PropertyResource::collection($query->paginate(15));
    }

    public function store(StorePropertyRequest $request): JsonResponse
    {
        $this->authorize('create', Property::class);

        $property = Property::query()->create([
            ...$request->validated(),
            'landlord_id' => $request->user()->id,
        ]);

        return response()->json(new PropertyResource($property), 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $property = Property::query()->with([
            'landlord',
            'leases' => $this->visibleLeases($user),
            'meters',
            'images',
        ])->find($id);
        if ($property === null) {
            abort(404);
        }

        $this->authorize('view', $property);

        return response()->json(new PropertyResource($property));
    }

    public function update(UpdatePropertyRequest $request, string $id): JsonResponse
    {
        $property = $this->findProperty($id);

        $this->authorize('update', $property);

        $property->update($request->validated());

        return response()->json(new PropertyResource($property));
    }

    public function destroy(string $id): JsonResponse
    {
        $property = $this->findProperty($id);

        $this->authorize('delete', $property);

        // aktivní smlouva by po smazání dál generovala platby a nájemník by ztratil přístup (#35)
        if ($property->leases()->where('status', LeaseStatus::Active)->exists()) {
            return response()->json([
                'message' => 'Property with an active lease cannot be deleted.',
            ], 422);
        }

        $property->delete();

        return response()->json([
            'message' => 'Property deleted successfully.',
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        $property = Property::withTrashed()->find($id);
        if ($property === null) {
            abort(404);
        }

        $this->authorize('restore', $property);

        $property->restore();

        return response()->json(new PropertyResource($property));
    }

    /**
     * Smlouvy v nemovitosti podle Lease::visibleTo (#20): vlastník všechny, nájemník svou, přiřazený správce
     * aktivní (částky skryje LeaseResource).
     */
    private function visibleLeases(User $user): Closure
    {
        return fn ($leases) => $leases->visibleTo($user)->with('tenant');
    }

    protected function getDateField(): string
    {
        return 'created_at';
    }
}
