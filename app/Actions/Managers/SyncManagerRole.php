<?php

namespace App\Actions\Managers;

use App\Enums\Role;
use App\Models\User;

/**
 * Přepočet users.role podle přiřazení (rozhodnutí z auditu, #14): kdo spravuje aspoň jednu (nesmazanou) nemovitost,
 * je správce, jinak nájemník. Stejné pravidlo jako UserPolicy::viewAny. Pronajímatel se nemění nikdy.
 */
final class SyncManagerRole
{
    public function handle(User $user): User
    {
        if ($user->isLandlord()) {
            return $user;
        }

        $user->forceFill(['role' => $user->managedProperties()->exists() ? Role::Manager : Role::Tenant])->save();

        return $user;
    }
}
