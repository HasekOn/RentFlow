<?php

namespace App\Http\Controllers\Api;

use App\Actions\Inventory\DeleteInventoryItem;
use App\Actions\Inventory\SaveInventoryItem;
use App\Http\Controllers\Api\Concerns\FindsProperty;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryItemRequest;
use App\Http\Requests\UpdateInventoryItemRequest;
use App\Http\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use Illuminate\Http\JsonResponse;

class InventoryItemController extends Controller
{
    use FindsProperty;

    public function index(string $propertyId): JsonResponse
    {
        $property = $this->findProperty($propertyId);
        $this->authorize('view', $property);

        $items = $property->inventoryItems()
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json(InventoryItemResource::collection($items));
    }

    public function store(StoreInventoryItemRequest $request, string $propertyId, SaveInventoryItem $save): JsonResponse
    {
        $property = $this->findProperty($propertyId);
        $this->authorize('create', [InventoryItem::class, $property]);

        $item = $save->handle($property, $request->validated());

        return response()->json(new InventoryItemResource($item), 201);
    }

    public function show(string $id): JsonResponse
    {
        $item = $this->findItem($id);
        $this->authorize('view', $item);

        return response()->json(new InventoryItemResource($item));
    }

    public function update(UpdateInventoryItemRequest $request, string $id, SaveInventoryItem $save): JsonResponse
    {
        $item = $this->findItem($id);
        $this->authorize('update', $item);

        $save->handle($item->property, $request->validated(), $item);

        return response()->json(new InventoryItemResource($item));
    }

    public function destroy(string $id, DeleteInventoryItem $delete): JsonResponse
    {
        $item = $this->findItem($id);
        $this->authorize('delete', $item);

        $delete->handle($item);

        return response()->json([
            'message' => 'Inventory item deleted successfully.',
        ]);
    }

    private function findItem(string $id): InventoryItem
    {
        $item = InventoryItem::query()->with('property')->find($id);
        if ($item === null) {
            abort(404);
        }

        return $item;
    }
}
