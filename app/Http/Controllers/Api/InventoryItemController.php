<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryItemRequest;
use App\Http\Requests\UpdateInventoryItemRequest;
use App\Models\InventoryItem;
use App\Models\Property;
use Illuminate\Http\JsonResponse;

class InventoryItemController extends Controller
{
    public function index(string $propertyId): JsonResponse
    {
        $property = Property::query()->findOrFail($propertyId);

        $items = $property->inventoryItems()
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json($items);
    }

    public function store(StoreInventoryItemRequest $request, string $propertyId): JsonResponse
    {
        $property = Property::query()->findOrFail($propertyId);

        if ($property->landlord_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You can only manage inventory for your own properties.',
            ], 403);
        }

        $item = InventoryItem::query()->create([
            ...$request->validated(),
            'property_id' => $property->id,
        ]);

        return response()->json($item, 201);
    }

    public function show(string $id): JsonResponse
    {
        $item = InventoryItem::with('property')->findOrFail($id);

        return response()->json($item);
    }

    public function update(UpdateInventoryItemRequest $request, string $id): JsonResponse
    {
        $item = InventoryItem::query()->findOrFail($id);

        $item->update($request->validated());

        return response()->json($item);
    }

    public function destroy(string $id): JsonResponse
    {
        $item = InventoryItem::query()->findOrFail($id);

        $item->delete();

        return response()->json([
            'message' => 'Inventory item deleted successfully.',
        ]);
    }
}
