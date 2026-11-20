<?php

namespace App\Actions\Meters;

use App\Models\MeterReading;

/**
 * Smazání odečtu. Autorizaci dělá volající.
 */
final class DeleteMeterReading
{
    public function handle(MeterReading $reading): void
    {
        $reading->delete();
    }
}
