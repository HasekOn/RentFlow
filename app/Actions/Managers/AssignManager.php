<?php

namespace App\Actions\Managers;

use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Povýšení na správce = přiřazení k nemovitosti pronajímatele (#14). Autorizaci dělá volající
 * (PropertyPolicy::assignManager, UserPolicy::promote).
 */
final class AssignManager
{
    public function __construct(private readonly SyncManagerRole $syncRole) {}

    public function handle(Property $property, User $user): User
    {
        return DB::transaction(function () use ($property, $user): User {
            $property->managers()->syncWithoutDetaching([$user->id]);

            return $this->syncRole->handle($user);
        });
    }
}
