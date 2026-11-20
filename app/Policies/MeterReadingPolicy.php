<?php

namespace App\Policies;

use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\User;

/**
 * Odečty: zapisuje vlastník a přiřazený správce (matice „Log meter reading“), maže jen vlastník.
 * Čtení odečtů se řídí MeterPolicy::view.
 */
class MeterReadingPolicy
{
    public function create(User $user, Meter $meter): bool
    {
        $property = $meter->property;
        if ($property === null) {
            return false;
        }

        return $property->landlord_id === $user->id
            || $property->managers()->whereKey($user->id)->exists();
    }

    public function delete(User $user, MeterReading $reading): bool
    {
        return $reading->meter?->property?->landlord_id === $user->id;
    }
}
