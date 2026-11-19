<?php

namespace App\Actions\Properties;

use App\Models\Property;
use App\Models\User;

/**
 * Založení nebo úprava nemovitosti. Při založení je vlastníkem ten, kdo ji zakládá. Autorizaci dělá volající.
 */
final class SaveProperty
{
    /**
     * @param  array<string, mixed>  $data  validovaná pole
     */
    public function handle(User $user, array $data, ?Property $property = null): Property
    {
        if ($property === null) {
            $property = new Property;
            $property->landlord_id = $user->id;
        }

        $property->fill($data)->save();

        return $property;
    }
}
