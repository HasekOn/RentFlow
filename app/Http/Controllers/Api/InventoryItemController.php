<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryItemController extends Controller
{
    public function index(string $propertyId): JsonResponse
    {
        $property = Property::findOrFail($propertyId);

        $items = $property->inventoryItems()
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json($items);
    }

    public function store(Request $request, string $propertyId): JsonResponse
    {
        $property = Property::findOrFail($propertyId);

        if ($property->landlord_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You can only manage inventory for your own properties.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string'],
            'condition' => ['sometimes', 'in:new,good,fair,poor,broken'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        $validated['property_id'] = $property->id;

        $item = InventoryItem::create($validated);

        return response()->json($item, 201);
    }

    public function show(string $id): JsonResponse
    {
        $item = InventoryItem::with('property')->findOrFail($id);

        return response()->json($item);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $item = InventoryItem::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['nullable', 'string'],
            'condition' => ['sometimes', 'in:new,good,fair,poor,broken'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        $item->update($validated);

        return response()->json($item);
    }

    public function destroy(string $id): JsonResponse
    {
        $item = InventoryItem::findOrFail($id);
        $item->delete();

        return response()->json([
            'message' => 'Inventory item deleted successfully.',
        ]);
    }
}
