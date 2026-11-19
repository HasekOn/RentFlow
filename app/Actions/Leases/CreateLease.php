<?php

namespace App\Actions\Leases;

use App\Enums\LeaseStatus;
use App\Enums\PropertyStatus;
use App\Events\LeaseCreated;
use App\Models\Lease;
use App\Models\Property;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nová smlouva v transakci (#53): kontroly, smlouva, obsazenost nemovitosti. Pozvánka nájemníkovi
 * (LeaseCreated) odchází až po commitu. Autorizaci dělá volající.
 */
final class CreateLease
{
    public function __construct(
        private readonly EnsureNoOverlappingLease $ensureNoOverlap,
        private readonly SyncPropertyOccupancy $syncOccupancy,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validovaná pole (property_id odpovídá $property)
     */
    public function handle(Property $property, array $data): Lease
    {
        return DB::transaction(function () use ($property, $data): Lease {
            if ($property->status === PropertyStatus::Renovation) {
                throw ValidationException::withMessages([
                    'property_id' => __('Cannot create lease for a property under renovation.'),
                ]);
            }

            $duplicate = Lease::query()
                ->where('property_id', $property->id)
                ->where('tenant_id', $data['tenant_id'])
                ->where('status', LeaseStatus::Active)
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'tenant_id' => __('An active lease already exists for this tenant on this property.'),
                ]);
            }

            $end = isset($data['end_date']) ? Carbon::parse($data['end_date']) : null;
            $this->ensureNoOverlap->handle($property->id, Carbon::parse($data['start_date']), $end);

            $lease = Lease::query()->create([...$data, 'property_id' => $property->id]);
            $lease->setRelation('property', $property);
            $this->syncOccupancy->handle($property);

            LeaseCreated::dispatch($lease);

            return $lease;
        });
    }
}
