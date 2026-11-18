<?php

namespace App\Policies;

use App\Models\Lease;
use App\Models\Rating;
use App\Models\User;

/**
 * Hodnocení nájemníka: vidí kdo vidí smlouvu (LeasePolicy::view), hodnotí a maže jen vlastník nemovitosti (#13).
 */
class RatingPolicy
{
    public function viewAny(User $user, Lease $lease): bool
    {
        return $user->can('view', $lease);
    }

    public function create(User $user, Lease $lease): bool
    {
        return $lease->property?->landlord_id === $user->id;
    }

    public function delete(User $user, Rating $rating): bool
    {
        return $rating->lease?->property?->landlord_id === $user->id;
    }
}
