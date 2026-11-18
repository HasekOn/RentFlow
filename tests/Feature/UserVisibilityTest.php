<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private Property $property;

    private User $activeTenant;

    private User $endedTenant;

    private User $manager;

    private User $coManager;

    private User $otherLandlord;

    private User $strangerTenant;

    private User $strangerManager;

    private User $tenantWithoutLease;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->landlord()->create();
        $this->property = Property::factory()->occupied()->create(['landlord_id' => $this->landlord->id]);
        $secondProperty = Property::factory()->create(['landlord_id' => $this->landlord->id]);

        $this->activeTenant = User::factory()->tenant()->create();
        Lease::factory()->create(['property_id' => $this->property->id, 'tenant_id' => $this->activeTenant->id]);
        $this->endedTenant = User::factory()->tenant()->create();
        Lease::factory()->ended()->create(['property_id' => $this->property->id, 'tenant_id' => $this->endedTenant->id]);

        $this->manager = User::factory()->manager()->create();
        $this->coManager = User::factory()->manager()->create();
        $this->property->managers()->attach([$this->manager->id, $this->coManager->id]);
        $secondProperty->managers()->attach($this->coManager->id);

        // cizí pronajímatel: nájemník, správce a správce našeho domu, který u něj bydlí
        $this->otherLandlord = User::factory()->landlord()->create();
        $foreignProperty = Property::factory()->occupied()->create(['landlord_id' => $this->otherLandlord->id]);
        $this->strangerTenant = User::factory()->tenant()->create();
        Lease::factory()->create(['property_id' => $foreignProperty->id, 'tenant_id' => $this->strangerTenant->id]);
        Lease::factory()->create(['property_id' => $foreignProperty->id, 'tenant_id' => $this->manager->id]);
        $this->strangerManager = User::factory()->manager()->create();
        $foreignProperty->managers()->attach($this->strangerManager->id);

        $this->tenantWithoutLease = User::factory()->tenant()->create();
    }

    public function test_landlord_sees_tenants_of_all_his_leases_and_his_managers(): void
    {
        $this->assertVisibleUsers($this->landlord, [
            $this->landlord, $this->activeTenant, $this->endedTenant, $this->manager, $this->coManager,
        ]);

        $this->actingAs($this->landlord)
            ->getJson($this->apiUrl('/users?role=manager'))
            ->assertStatus(200)
            ->assertJsonCount(2);
    }

    public function test_manager_sees_active_tenants_of_managed_properties_and_co_managers(): void
    {
        $this->assertVisibleUsers($this->manager, [$this->manager, $this->activeTenant, $this->coManager]);

        foreach ([$this->endedTenant, $this->strangerTenant, $this->strangerManager, $this->otherLandlord] as $user) {
            $this->assertProfileStatus($this->manager, $user, 403);
        }
        $this->assertProfileStatus($this->manager, $this->activeTenant, 200);
    }

    public function test_tenant_cannot_list_people_and_sees_only_own_trust_score_and_ratings(): void
    {
        Rating::factory()->create(['lease_id' => $this->activeTenant->leases()->value('id'), 'rated_by' => $this->landlord->id]);

        $this->actingAs($this->activeTenant)->getJson($this->apiUrl('/users'))->assertStatus(403);
        $this->assertProfileStatus($this->activeTenant, $this->activeTenant, 200);
        $this->actingAs($this->activeTenant)
            ->getJson($this->apiUrl("/users/{$this->activeTenant->id}/ratings"))
            ->assertJsonCount(1)
            ->assertJsonPath('0.rated_by.name', $this->landlord->name)
            ->assertJsonMissingPath('0.rated_by.email');

        foreach ([$this->endedTenant, $this->strangerTenant, $this->manager] as $user) {
            $this->assertProfileStatus($this->activeTenant, $user, 403);
        }
    }

    public function test_tenant_without_lease_and_other_landlord_see_nothing_foreign(): void
    {
        $this->actingAs($this->tenantWithoutLease)->getJson($this->apiUrl('/users'))->assertStatus(403);
        $this->assertProfileStatus($this->tenantWithoutLease, $this->activeTenant, 403);
        $this->assertProfileStatus($this->tenantWithoutLease, $this->tenantWithoutLease, 200);

        $this->assertProfileStatus($this->otherLandlord, $this->activeTenant, 403);
        $this->assertProfileStatus($this->landlord, $this->strangerTenant, 403);
        $this->assertProfileStatus($this->landlord, $this->strangerManager, 403);
    }

    public function test_landlord_finds_account_by_exact_email_with_minimal_data(): void
    {
        $url = fn (string $email) => $this->apiUrl('/users/lookup?email='.urlencode($email));

        $this->actingAs($this->landlord)
            ->getJson($url($this->strangerTenant->email))
            ->assertStatus(200)
            ->assertExactJson([
                'id' => $this->strangerTenant->id,
                'name' => $this->strangerTenant->name,
                'email' => $this->strangerTenant->email,
                'role' => Role::Tenant->value,
            ]);

        $this->actingAs($this->landlord)->getJson($url(substr($this->strangerTenant->email, 1)))->assertStatus(404);
        $this->actingAs($this->landlord)->getJson($url($this->otherLandlord->email))->assertStatus(404);
        $this->actingAs($this->landlord)->getJson($this->apiUrl('/users/lookup'))->assertStatus(422);

        foreach ([$this->manager, $this->activeTenant] as $user) {
            $this->actingAs($user)->getJson($url($this->strangerTenant->email))->assertStatus(403);
        }
    }

    public function test_role_filter_is_validated(): void
    {
        $this->actingAs($this->landlord)
            ->getJson($this->apiUrl('/users?role=boss'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');
    }

    public function test_people_query_count_does_not_depend_on_user_count(): void
    {
        $few = $this->countPeopleQueries();
        foreach (User::factory()->tenant()->count(10)->create() as $tenant) {
            Lease::factory()->create(['property_id' => $this->property->id, 'tenant_id' => $tenant->id]);
        }

        $this->assertSame($few, $this->countPeopleQueries());
    }

    /**
     * @param  list<User>  $expected
     */
    private function assertVisibleUsers(User $viewer, array $expected): void
    {
        $expectedIds = array_map(fn (User $user) => $user->id, $expected);
        sort($expectedIds);

        $ids = $this->actingAs($viewer)->getJson($this->apiUrl('/users'))->assertStatus(200)->json('*.id');
        sort($ids);

        $this->assertSame($expectedIds, $ids);
    }

    /**
     * Detail osoby = trust score + hodnocení, obě routy se řídí UserPolicy::view.
     */
    private function assertProfileStatus(User $viewer, User $user, int $status): void
    {
        // trust score počítá jen nájemníky a správce, u pronajímatele je 404 dřív než autorizace
        if (! $user->isLandlord()) {
            $this->actingAs($viewer)->getJson($this->apiUrl("/tenants/{$user->id}/trust-score"))->assertStatus($status);
        }
        $this->actingAs($viewer)->getJson($this->apiUrl("/users/{$user->id}/ratings"))->assertStatus($status);
    }

    private function countPeopleQueries(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->landlord)->getJson($this->apiUrl('/users'))->assertStatus(200);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
