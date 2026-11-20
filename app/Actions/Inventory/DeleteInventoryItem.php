<?php

namespace App\Actions\Inventory;

use App\Models\InventoryItem;

/**
 * Smazání položky inventáře. Autorizaci dělá volající.
 */
final class DeleteInventoryItem
{
    public function handle(InventoryItem $item): void
    {
        $item->delete();
    }
}
