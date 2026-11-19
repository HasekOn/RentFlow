<?php

namespace App\Actions\Users;

use App\Models\User;

/**
 * Úprava vlastního profilu (jméno, e-mail, telefon). Role a trust score nejsou fillable.
 */
final class UpdateProfile
{
    /**
     * @param  array<string, mixed>  $data  validovaná pole
     */
    public function handle(User $user, array $data): User
    {
        $user->update($data);

        return $user;
    }
}
