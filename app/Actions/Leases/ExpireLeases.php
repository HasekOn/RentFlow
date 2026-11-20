<?php

namespace App\Actions\Leases;

use App\Enums\LeaseStatus;
use App\Enums\PropertyStatus;
use App\Models\Lease;
use App\Models\Property;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Scheduler: aktivní smlouvy po konci přejdou na `ended` a obsazené nemovitosti bez aktivní smlouvy se uvolní.
 * Dvěma hromadnými dotazy (#48), ne dotazem na každou nemovitost.
 */
final class ExpireLeases
{
    /**
     * @return array{expired: int, freed: int}
     */
    public function handle(CarbonInterface $today): array
    {
        return DB::transaction(function () use ($today): array {
            $expired = Lease::query()
                ->where('status', LeaseStatus::Active)
                ->whereNotNull('end_date')
                ->where('end_date', '<', $today->copy()->startOfDay())
                ->update(['status' => LeaseStatus::Ended]);

            $freed = Property::query()
                ->where('status', PropertyStatus::Occupied)
                ->whereDoesntHave('leases', fn ($leases) => $leases->where('status', LeaseStatus::Active))
                ->update(['status' => PropertyStatus::Available]);

            return ['expired' => $expired, 'freed' => $freed];
        });
    }
}
