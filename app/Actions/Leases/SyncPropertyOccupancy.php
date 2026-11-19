<?php

namespace App\Actions\Leases;

use App\Enums\LeaseStatus;
use App\Enums\PropertyStatus;
use App\Models\Property;

/**
 * Jedno místo pro stav obsazenosti nemovitosti podle aktivních smluv (#53): s aktivní smlouvou `occupied`,
 * bez ní `available`. Rekonstrukci nechá být (o té rozhoduje pronajímatel).
 */
final class SyncPropertyOccupancy
{
    public function handle(Property $property): void
    {
        if ($property->status === PropertyStatus::Renovation) {
            return;
        }

        $occupied = $property->leases()->where('status', LeaseStatus::Active)->exists();
        $status = $occupied ? PropertyStatus::Occupied : PropertyStatus::Available;

        if ($property->status !== $status) {
            $property->update(['status' => $status]);
        }
    }
}
