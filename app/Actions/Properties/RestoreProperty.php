<?php

namespace App\Actions\Properties;

use App\Models\Property;

/**
 * Obnovení smazané nemovitosti. Stav zůstává uložený (smazat šlo jen bez aktivní smlouvy).
 */
final class RestoreProperty
{
    public function handle(Property $property): Property
    {
        $property->restore();

        return $property;
    }
}
