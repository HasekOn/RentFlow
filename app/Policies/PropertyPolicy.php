<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    /**
     * Seznam vidí každý přihlášený, obsah omezuje Property::visibleTo.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Vlastník, přiřazený správce nebo nájemník s aktivní smlouvou (jedna pravda se scope visibleTo).
     */
    public function view(User $user, Property $property): bool
    {
        return $property->landlord_id === $user->id
            || Property::query()->visibleTo($user)->whereKey($property->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->isLandlord();
    }

    public function update(User $user, Property $property): bool
    {
        return $property->landlord_id === $user->id;
    }

    /**
     * Přiřazení a odebrání správce (i seznam správců s kontakty): jen vlastník (#14).
     */
    public function assignManager(User $user, Property $property): bool
    {
        return $property->landlord_id === $user->id;
    }

    public function delete(User $user, Property $property): bool
    {
        return $property->landlord_id === $user->id;
    }

    public function restore(User $user, Property $property): bool
    {
        return $property->landlord_id === $user->id;
    }

    public function forceDelete(User $user, Property $property): bool
    {
        return false;
    }
}
