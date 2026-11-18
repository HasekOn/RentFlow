<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Property;

/**
 * Načtení nemovitosti z URL pro endpointy pod ní (find + 404). Autorizaci dělá volající (view, update …).
 */
trait FindsProperty
{
    protected function findProperty(string $id): Property
    {
        $property = Property::query()->find($id);
        if ($property === null) {
            abort(404);
        }

        return $property;
    }
}
