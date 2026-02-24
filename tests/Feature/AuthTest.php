<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson($this->apiUrl('/register'), [
            'name' => 'Test User',
            'email' => 'test@rentflow.cz',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'landlord',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role'],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@rentflow.cz',
            'role' => 'landlord',
        ]);
    }

    public function test_register_requires_valid_data(): void
    {
        $response = $this->postJson($this->apiUrl('/register'), [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['name', 'email', 'password'],
            ]);
    }

    public function test_register_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'taken@rentflow.cz']);

        $response = $this->postJson($this->apiUrl('/register'), [
            'name' => 'Another User',
            'email' => 'taken@rentflow.cz',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_default_role_is_tenant(): void
    {
        $response = $this->postJson($this->apiUrl('/register'), [
            'name' => 'Default Role',
            'email' => 'default@rentflow.cz',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.role', 'tenant');
    }

    public function test_user_can_login(): void
    {
        User::factory()->create([
            'email' => 'login@rentflow.cz',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson($this->apiUrl('/login'), [
            'email' => 'login@rentflow.cz',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role'],
                'token',
            ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'wrong@rentflow.cz',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson($this->apiUrl('/login'), [
            'email' => 'wrong@rentflow.cz',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson($this->apiUrl('/login'), [
            'email' => 'nobody@rentflow.cz',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson($this->apiUrl('/logout'));

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Logged out successfully.');
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson(($this->apiUrl('/logout')));

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->landlord()->create();

        $response = $this->actingAs($user)
            ->getJson(($this->apiUrl('/user')));

        $response->assertStatus(200)
            ->assertJsonMissing(['password']);

        // Resource returned directly wraps in 'data', json() does not
        $json = $response->json();
        $userData = $json['data'] ?? $json;
        $this->assertEquals($user->id, $userData['id']);
        $this->assertEquals('landlord', $userData['role']);
    }

    public function test_unauthenticated_user_cannot_get_profile(): void
    {
        $response = $this->getJson(($this->apiUrl('/user')));

        $response->assertStatus(401);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson($this->apiUrl('/login'), [
                'email' => 'fake@test.cz',
                'password' => 'wrong',
            ]);
        }

        $response = $this->postJson($this->apiUrl('/login'), [
            'email' => 'fake@test.cz',
            'password' => 'wrong',
        ]);

        $response->assertStatus(429)
            ->assertJsonPath('error', 'too_many_requests');
    }
}
