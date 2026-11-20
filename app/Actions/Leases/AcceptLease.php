<?php

namespace App\Actions\Leases;

use App\Enums\LeaseStatus;
use App\Enums\PropertyStatus;
use App\Models\Lease;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nájemník přijme pozvánku (S1): smlouva je aktivní, od teď se počítá do Trust Score a pronajímatel vidí jeho
 * kontakty. Překryv s jinou aktivní smlouvou a obsazenost nemovitosti se řeší tady (ze dvou pozvánek vyhraje
 * ta přijatá dřív). Autorizaci (jen nájemník smlouvy) dělá volající.
 */
final class AcceptLease
{
    public function __construct(
        private readonly EnsureNoOverlappingLease $ensureNoOverlap,
        private readonly SyncPropertyOccupancy $syncOccupancy,
    ) {}

    public function handle(Lease $lease): Lease
    {
        return DB::transaction(function () use ($lease): Lease {
            $lease->refresh();

            if ($lease->status !== LeaseStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => __('Only a pending lease invitation can be accepted or declined.'),
                ]);
            }

            $property = $lease->property;
            if ($property === null || $property->status === PropertyStatus::Renovation) {
                throw ValidationException::withMessages([
                    'status' => __('Cannot activate a lease for a property under renovation.'),
                ]);
            }

            if ($lease->end_date !== null && $lease->end_date->lt(today())) {
                throw ValidationException::withMessages([
                    'end_date' => __('This lease has already ended and cannot be accepted.'),
                ]);
            }

            $this->ensureNoOverlap->handle($lease->property_id, $lease->start_date, $lease->end_date, $lease->id);

            $lease->forceFill(['status' => LeaseStatus::Active, 'accepted_at' => now()])->save();
            $this->syncOccupancy->handle($property);

            return $lease;
        });
    }
}
