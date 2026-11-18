<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Meter;

/**
 * Načtení měřiče z URL i s nemovitostí pro policy (find + 404). Autorizaci dělá volající.
 */
trait FindsMeter
{
    protected function findMeter(string $id): Meter
    {
        $meter = Meter::query()->with('property')->find($id);
        if ($meter === null) {
            abort(404);
        }

        return $meter;
    }
}
