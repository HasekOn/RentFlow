<?php

namespace App\Actions\Leases;

use App\Enums\LeaseStatus;
use App\Models\Lease;
use App\Notifications\LeaseExpiringNotification;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scheduler (denně): upozornění nájemníkovi a vlastníkovi, že smlouva končí. Jen 30 a 7 dní předem (#33),
 * dřív chodilo každý den celý měsíc. Smlouvy smazaných nemovitostí se přeskočí.
 */
final class NotifyExpiringLeases
{
    /** Kolik dní před koncem smlouvy se upozorňuje. */
    public const array DAYS_BEFORE = [30, 7];

    /**
     * @return int počet smluv, ke kterým odešlo upozornění
     */
    public function handle(CarbonInterface $today): int
    {
        $leases = Lease::query()
            ->with(['property.landlord', 'tenant'])
            ->where('status', LeaseStatus::Active)
            ->whereHas('property')
            ->where(function (Builder $query) use ($today): void {
                foreach (self::DAYS_BEFORE as $days) {
                    $day = $today->copy()->startOfDay()->addDays($days);
                    $query->orWhere(fn (Builder $onDay) => $onDay
                        ->where('end_date', '>=', $day)
                        ->where('end_date', '<', $day->copy()->addDay()));
                }
            })
            ->get();

        foreach ($leases as $lease) {
            $lease->tenant?->notify(new LeaseExpiringNotification($lease));
            $lease->property?->landlord?->notify(new LeaseExpiringNotification($lease));
        }

        return $leases->count();
    }
}
