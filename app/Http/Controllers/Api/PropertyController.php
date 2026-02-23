<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Property::class);

        $validated = $request->validate([
            'address' => ['required', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'size' => ['required', 'numeric', 'min:1'],
            'disposition' => ['required', 'string', 'max:50'],
            'floor' => ['nullable', 'integer'],
            'status' => ['sometimes', 'in:available,occupied,renovation'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['landlord_id'] = $request->user()->id;

        $property = Property::create($validated);

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

    public function update(Request $request, string $id): JsonResponse
    {
        $property = Property::query()->findOrFail($id);

        $this->authorize('update', $property);

        $validated = $request->validate([
            'address' => ['sometimes', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'size' => ['sometimes', 'numeric', 'min:1'],
            'disposition' => ['sometimes', 'string', 'max:50'],
            'floor' => ['nullable', 'integer'],
            'status' => ['sometimes', 'in:available,occupied,renovation'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $property->update($validated);

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
