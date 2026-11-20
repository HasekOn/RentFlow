<?php

namespace App\Actions\Leases;

use App\Enums\LeaseStatus;
use App\Models\Lease;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nájemník odmítne pozvánku (S1). Odmítnutou smlouvu už nejde měnit, pronajímatel ji může jen smazat.
 * Autorizaci (jen nájemník smlouvy) dělá volající.
 */
final class DeclineLease
{
    public function handle(Lease $lease): Lease
    {
        return DB::transaction(function () use ($lease): Lease {
            $lease->refresh();

            if ($lease->status !== LeaseStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => __('Only a pending lease invitation can be accepted or declined.'),
                ]);
            }

            $lease->update(['status' => LeaseStatus::Declined]);

            return $lease;
        });
    }
}
