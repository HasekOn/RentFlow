<?php

namespace App\Actions\Properties;

use App\Enums\LeaseStatus;
use App\Models\Property;
use Illuminate\Validation\ValidationException;

/**
 * Smazání (soft delete) nemovitosti. Aktivní smlouva by dál generovala platby a nájemník by ztratil přístup (#35).
 */
final class DeleteProperty
{
    public function handle(Property $property): void
    {
        if ($property->leases()->where('status', LeaseStatus::Active)->exists()) {
            throw ValidationException::withMessages([
                'property' => __('Property with an active lease cannot be deleted.'),
            ]);
        }

        $property->delete();
    }
}
