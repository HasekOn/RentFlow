<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Lease;

/**
 * Načtení smlouvy (z URL nebo z lease_id v těle) i s nemovitostí pro policy (find + 404). Autorizaci dělá volající.
 */
trait FindsLease
{
    protected function findLease(string $id): Lease
    {
        $lease = Lease::query()->with('property')->find($id);
        if ($lease === null) {
            abort(404);
        }

        return $lease;
    }
}
