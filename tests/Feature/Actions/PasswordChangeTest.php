<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Změna hesla (část #25): ostatní přihlášení (tokeny) se zruší, to aktuální zůstane.
 */
class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_change_revokes_other_tokens(): void
    {
        $user = User::factory()->tenant()->create();
        $current = $user->createToken('telefon')->plainTextToken;
        $user->createToken('notebook');

        $this->withToken($current)
            ->putJson($this->apiUrl('/profile/password'), [
                'current_password' => 'password',
                'password' => 'nove-heslo-123',
                'password_confirmation' => 'nove-heslo-123',
            ])
            ->assertStatus(200);

        $this->assertSame(['telefon'], $user->tokens()->pluck('name')->all());
        $this->assertTrue(Hash::check('nove-heslo-123', (string) $user->refresh()->password));
    }

    public function test_wrong_current_password_changes_nothing(): void
    {
        $user = User::factory()->tenant()->create();
        $user->createToken('notebook');

        $this->actingAs($user)
            ->putJson($this->apiUrl('/profile/password'), [
                'current_password' => 'spatne',
                'password' => 'nove-heslo-123',
                'password_confirmation' => 'nove-heslo-123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertSame(1, $user->tokens()->count());
    }
}
