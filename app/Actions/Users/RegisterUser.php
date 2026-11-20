<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;

/**
 * Registrace pronajímatele nebo nájemníka. Správcem se uživatel stává jen přiřazením od pronajímatele
 * (AssignManager), proto se role Manager odmítne. Heslo hashuje cast `hashed` na modelu.
 */
final class RegisterUser
{
    /**
     * @param  array{name: string, email: string, password: string, role?: string}  $data  validovaná pole
     */
    public function handle(array $data): User
    {
        $role = Role::tryFrom($data['role'] ?? '') ?? Role::Tenant;

        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        $user->forceFill(['role' => $role === Role::Manager ? Role::Tenant : $role])->save();

        return $user;
    }
}
