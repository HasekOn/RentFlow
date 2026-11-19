<?php

namespace App\Actions\Meters;

use App\Models\Meter;
use App\Models\Property;

/**
 * Založení měřiče v nemovitosti nebo jeho úprava. Autorizaci dělá volající.
 */
final class SaveMeter
{
    /**
     * @param  array<string, mixed>  $data  validovaná pole
     */
    public function handle(Property $property, array $data, ?Meter $meter = null): Meter
    {
        $meter ??= new Meter(['property_id' => $property->id]);
        $meter->fill($data)->save();

        return $meter;
    }
}
