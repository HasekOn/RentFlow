<?php

namespace App\Policies;

use App\Models\Meter;
use App\Models\Property;
use App\Models\User;

/**
 * Měřiče: čtení jako nemovitost (PropertyPolicy::view), zakládání, úpravy a mazání jen vlastník.
 */
class MeterPolicy
{
    public function view(User $user, Meter $meter): bool
    {
        return $meter->property !== null && $user->can('view', $meter->property);
    }

    public function create(User $user, Property $property): bool
    {
        return $property->landlord_id === $user->id;
    }

    public function update(User $user, Meter $meter): bool
    {
        return $meter->property?->landlord_id === $user->id;
    }

    public function delete(User $user, Meter $meter): bool
    {
        return $meter->property?->landlord_id === $user->id;
    }
}
