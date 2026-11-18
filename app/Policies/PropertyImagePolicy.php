<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\User;

/**
 * Fotky nemovitosti: čtení jako nemovitost (PropertyPolicy::view), zápis jen vlastník (matice „Properties CRUD“).
 */
class PropertyImagePolicy
{
    public function create(User $user, Property $property): bool
    {
        return $property->landlord_id === $user->id;
    }

    public function update(User $user, PropertyImage $image): bool
    {
        return $image->property?->landlord_id === $user->id;
    }

    public function delete(User $user, PropertyImage $image): bool
    {
        return $image->property?->landlord_id === $user->id;
    }
}
