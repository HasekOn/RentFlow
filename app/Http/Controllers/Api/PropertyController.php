<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Property::class);

        $user = $request->user();

        if ($user->role === 'landlord') {
            $properties = $user->ownedProperties()->with('leases.tenant')->get();
        } else {
            $propertyIds = $user->leases()
                ->where('status', 'active')
                ->pluck('property_id');

            $properties = Property::query()->whereIn('id', $propertyIds)->get();
        }

        return response()->json($properties);
    }

    public function store(StorePropertyRequest $request): JsonResponse
    {
        $this->authorize('create', Property::class);

        $property = Property::query()->create([
            ...$request->validated(),
            'landlord_id' => $request->user()->id,
        ]);

        return response()->json($property, 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $property = Property::with([
            'landlord',
            'leases.tenant',
            'meters',
            'images',
        ])->findOrFail($id);

        $this->authorize('view', $property);

        return response()->json($property);
    }

    public function update(UpdatePropertyRequest $request, string $id): JsonResponse
    {
        $property = Property::query()->findOrFail($id);
        $this->authorize('update', $property);

        $property->update($request->validated());

        return response()->json($property);
    }

    public function destroy(string $id): JsonResponse
    {
        $property = Property::query()->findOrFail($id);

        $this->authorize('delete', $property);

        $property->delete();

        return response()->json([
            'message' => 'Property deleted successfully.',
        ]);
    }
}
