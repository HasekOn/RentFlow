<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_cannot_change_role_or_trust_score(): void
    {
        $tenant = User::factory()->tenant()->create();
        $score = $tenant->trust_score;

        $this->actingAs($tenant)->putJson($this->apiUrl('/profile'), [
            'name' => 'Nové jméno',
            'role' => 'landlord',
            'trust_score' => 100,
        ])->assertStatus(200);

        $tenant->refresh();
        $this->assertSame('Nové jméno', $tenant->name);
        $this->assertSame(Role::Tenant, $tenant->role);
        $this->assertSame($score, $tenant->trust_score);
    }

    /**
     * API routy od 2.7 middleware role nepoužívají (rozhodují policies), middleware zůstává jako hrubé síto.
     * Proto testovací routa místo dřívějšího POST /expenses.
     */
    public function test_role_middleware_does_not_reveal_required_roles(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'role:landlord'])->get('/_test/role-middleware', fn () => 'ok');
        $tenant = User::factory()->tenant()->create();

        $response = $this->actingAs($tenant)->getJson('/_test/role-middleware');

        $response->assertStatus(403)->assertExactJson(['message' => 'Access denied.']);
        $this->actingAs(User::factory()->landlord()->create())->get('/_test/role-middleware')->assertOk();
    }
}
