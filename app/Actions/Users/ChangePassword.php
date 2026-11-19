<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Změna hesla po ověření současného. Ostatní API tokeny se smažou (část #25): kdo heslo znal nebo ukradl token,
 * přijde o přístup. Token, kterým se mění, zůstane. Heslo hashuje cast `hashed` na modelu.
 */
final class ChangePassword
{
    public function handle(User $user, string $currentPassword, string $newPassword, ?int $keepTokenId = null): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('The current password is incorrect.'),
            ]);
        }

        DB::transaction(function () use ($user, $newPassword, $keepTokenId): void {
            $user->update(['password' => $newPassword]);
            $user->tokens()->when($keepTokenId !== null, fn ($tokens) => $tokens->whereKeyNot($keepTokenId))->delete();
        });
    }
}
