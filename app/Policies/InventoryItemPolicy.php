<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\Property;
use App\Models\User;

/**
 * Inventář: čtení jako nemovitost (PropertyPolicy::view), zakládání, úpravy a mazání jen vlastník (#9).
 */
class InventoryItemPolicy
{
    public function view(User $user, InventoryItem $item): bool
    {
        return $item->property !== null && $user->can('view', $item->property);
    }

    public function create(User $user, Property $property): bool
    {
        return $property->landlord_id === $user->id;
    }

    public function update(User $user, InventoryItem $item): bool
    {
        return $item->property?->landlord_id === $user->id;
    }

    public function delete(User $user, InventoryItem $item): bool
    {
        return $item->property?->landlord_id === $user->id;
    }
}
