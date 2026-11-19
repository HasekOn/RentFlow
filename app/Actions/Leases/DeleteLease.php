<?php

namespace App\Actions\Leases;

use App\Models\Lease;
use Illuminate\Support\Facades\DB;

/**
 * Smazání (soft delete) smlouvy a přepočet obsazenosti nemovitosti. Autorizaci dělá volající.
 */
final class DeleteLease
{
    public function __construct(private readonly SyncPropertyOccupancy $syncOccupancy) {}

    public function handle(Lease $lease): void
    {
        DB::transaction(function () use ($lease): void {
            $lease->delete();

            if ($lease->property !== null) {
                $this->syncOccupancy->handle($lease->property);
            }
        });
    }
}
