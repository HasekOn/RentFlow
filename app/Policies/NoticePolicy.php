<?php

namespace App\Policies;

use App\Models\Notice;
use App\Models\Property;
use App\Models\User;

/**
 * Oznámení: čtení jako nemovitost (PropertyPolicy::view, i přiřazený správce), zápis jen vlastník nemovitosti.
 */
class NoticePolicy
{
    public function view(User $user, Notice $notice): bool
    {
        return $notice->property !== null && $user->can('view', $notice->property);
    }

    public function create(User $user, Property $property): bool
    {
        return $property->landlord_id === $user->id;
    }

    public function update(User $user, Notice $notice): bool
    {
        return $notice->property?->landlord_id === $user->id;
    }

    public function delete(User $user, Notice $notice): bool
    {
        return $notice->property?->landlord_id === $user->id;
    }
}
