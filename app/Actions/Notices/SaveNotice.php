<?php

namespace App\Actions\Notices;

use App\Models\Notice;
use App\Models\Property;
use App\Models\User;

/**
 * Založení oznámení v nemovitosti (autorem je ten, kdo ho zakládá) nebo jeho úprava. Autorizaci dělá volající.
 */
final class SaveNotice
{
    /**
     * @param  array<string, mixed>  $data  validovaná pole
     */
    public function handle(Property $property, User $user, array $data, ?Notice $notice = null): Notice
    {
        $notice ??= new Notice(['property_id' => $property->id, 'created_by' => $user->id]);
        $notice->fill($data)->save();

        return $notice;
    }
}
