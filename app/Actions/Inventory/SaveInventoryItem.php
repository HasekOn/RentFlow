<?php

namespace App\Actions\Inventory;

use App\Models\InventoryItem;
use App\Models\Property;

/**
 * Založení položky inventáře v nemovitosti nebo její úprava. Autorizaci dělá volající.
 */
final class SaveInventoryItem
{
    /**
     * @param  array<string, mixed>  $data  validovaná pole
     */
    public function handle(Property $property, array $data, ?InventoryItem $item = null): InventoryItem
    {
        $item ??= new InventoryItem(['property_id' => $property->id]);
        $item->fill($data)->save();

        return $item;
    }
}
