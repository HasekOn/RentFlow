<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Úprava vlastního profilu (jméno, e-mail, telefon). Role a trust score nejsou fillable. Změna e-mailu jen se
 * současným heslem (S9): ukradený token sám nestačí na převzetí účtu přes e-mail.
 */
final class UpdateProfile
{
    /**
     * @param  array<string, mixed>  $data  validovaná pole (current_password jen pro kontrolu, neukládá se)
     */
    public function handle(User $user, array $data): User
    {
        $currentPassword = $data['current_password'] ?? null;
        unset($data['current_password']);

        $user->fill($data);
        if ($user->isDirty('email') && (! is_string($currentPassword) || ! Hash::check($currentPassword, $user->password))) {
            throw ValidationException::withMessages([
                'current_password' => __('Enter your current password to change your email.'),
            ]);
        }
        $user->save();

        return $user;
    }
}
