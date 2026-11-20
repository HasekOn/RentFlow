<?php

namespace App\Http\Controllers\Api;

use App\Actions\Properties\DeleteProperty;
use App\Actions\Properties\RestoreProperty;
use App\Actions\Properties\SaveProperty;
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
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PropertyController extends Controller
{
    use Filterable, FindsProperty;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Property::class);

        /** @var User $user */
        $user = $this->currentUser($request);

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

    public function store(StorePropertyRequest $request, SaveProperty $save): JsonResponse
    {
        $this->authorize('create', Property::class);

        $property = $save->handle($this->currentUser($request), $request->validated());

        return response()->json(new PropertyResource($property), 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->currentUser($request);

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

    public function update(UpdatePropertyRequest $request, string $id, SaveProperty $save): JsonResponse
    {
        $property = $this->findProperty($id);

        $this->authorize('update', $property);

        $save->handle($this->currentUser($request), $request->validated(), $property);

        return response()->json(new PropertyResource($property));
    }

    public function destroy(string $id, DeleteProperty $delete): JsonResponse
    {
        $property = $this->findProperty($id);

        $this->authorize('delete', $property);

        $delete->handle($property);

        return response()->json([
            'message' => 'Property deleted successfully.',
        ]);
    }

    public function restore(string $id, RestoreProperty $restore): JsonResponse
    {
        $property = Property::withTrashed()->find($id);
        if ($property === null) {
            abort(404);
        }

        $this->authorize('restore', $property);

        $restore->handle($property);

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
}
