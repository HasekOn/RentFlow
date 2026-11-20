<?php

namespace App\Actions\Leases;

use App\Enums\LeaseStatus;
use App\Enums\PropertyStatus;
use App\Models\Lease;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Úprava smlouvy včetně přechodů stavu (#30, #22, #53): konec po uloženém začátku, překryv aktivních smluv,
 * reaktivace jen mimo rekonstrukci a obsazenost nemovitosti po každé změně stavu. Nepřijatou pozvánku (S1)
 * aktivuje jen nájemník přijetím, pronajímatel ji může jen odvolat (`terminated`), ne upravit: nájemník by přijal
 * jiné podmínky, než mu přišly e-mailem. Odmítnutá smlouva se nemění.
 * Autorizaci dělá volající.
 */
final class UpdateLease
{
    public function __construct(
        private readonly EnsureNoOverlappingLease $ensureNoOverlap,
        private readonly SyncPropertyOccupancy $syncOccupancy,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validovaná pole
     */
    public function handle(Lease $lease, array $data): Lease
    {
        return DB::transaction(function () use ($lease, $data): Lease {
            $oldStatus = $lease->status;
            if ($oldStatus === LeaseStatus::Declined) {
                throw ValidationException::withMessages([
                    'status' => __('A declined lease cannot be changed, only deleted.'),
                ]);
            }

            $lease->fill($data);

            $withdrawn = $lease->status === LeaseStatus::Terminated && array_keys($lease->getDirty()) === ['status'];
            if ($oldStatus === LeaseStatus::Pending && $lease->isDirty() && ! $withdrawn) {
                throw ValidationException::withMessages([
                    'status' => __('A pending invitation cannot be changed. Withdraw it and send a new one.'),
                ]);
            }

            if (! $lease->isAccepted() && $lease->isDirty('status') && $lease->status !== LeaseStatus::Terminated) {
                throw ValidationException::withMessages([
                    'status' => __('Only the tenant can activate a lease by accepting the invitation. You can only withdraw it.'),
                ]);
            }

            // konec po začátku i proti uloženému start_date (#30)
            if ($lease->isDirty(['start_date', 'end_date'])) {
                Validator::make(
                    ['end_date' => $lease->end_date?->toDateString()],
                    ['end_date' => ['nullable', 'after:'.$lease->start_date->toDateString()]],
                )->validate();
            }

            $reactivated = $lease->status === LeaseStatus::Active && $oldStatus !== LeaseStatus::Active;
            if ($reactivated && $lease->property?->status === PropertyStatus::Renovation) {
                throw ValidationException::withMessages([
                    'status' => __('Cannot activate a lease for a property under renovation.'),
                ]);
            }

            // překryv hlídat při změně období a při reaktivaci (#22)
            if ($lease->status === LeaseStatus::Active && ($reactivated || $lease->isDirty(['start_date', 'end_date']))) {
                $this->ensureNoOverlap->handle($lease->property_id, $lease->start_date, $lease->end_date, $lease->id);
            }

            $lease->save();

            if ($lease->status !== $oldStatus && $lease->property !== null) {
                $this->syncOccupancy->handle($lease->property);
            }

            return $lease;
        });
    }
}
