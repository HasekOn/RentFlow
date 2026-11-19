<?php

namespace App\Actions\Meters;

use App\Models\Meter;

/**
 * Smazání měřiče. Autorizaci dělá volající.
 */
final class DeleteMeter
{
    public function handle(Meter $meter): void
    {
        $meter->delete();
    }
}
