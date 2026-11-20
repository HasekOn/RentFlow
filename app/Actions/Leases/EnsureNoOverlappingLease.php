<?php

namespace App\Actions\Leases;

use App\Enums\LeaseStatus;
use App\Models\Lease;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Na jedné nemovitosti nesmí být aktivní smlouvy s překrývajícím se obdobím (rozhodnutí z auditu, #22).
 * Sdílí ji založení i úprava smlouvy.
 */
final class EnsureNoOverlappingLease
{
    public function handle(int $propertyId, CarbonInterface $start, ?CarbonInterface $end, ?int $ignoreId = null): void
    {
        $overlaps = Lease::query()
            ->where('property_id', $propertyId)
            ->where('status', LeaseStatus::Active)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->overlapping($start, $end)
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'start_date' => __('Another active lease on this property overlaps the given period.'),
            ]);
        }
    }
}
