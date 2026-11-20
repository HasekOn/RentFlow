<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Concerns\PropertyRoles;
use Tests\TestCase;

/**
 * Správci (2.11, #14): povýšení = přiřazení k vlastní nemovitosti, jen koho pronajímatel vidí; sesazení odebere jen
 * přiřazení u něj; users.role se přepočítá. Správce je zároveň nájemníkem u druhého pronajímatele ($managerLease).
 */
class ManagerAssignmentTest extends TestCase
{
    use PropertyRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createPropertyRoles();
    }

    public function test_landlord_promotes_own_tenant_to_manager_of_own_property(): void
    {
        $this->promote($this->landlord, $this->tenant, $this->property->id)
            ->assertStatus(200)
            ->assertJsonPath('user.role', Role::Manager->value);

        $this->assertSame(Role::Manager, $this->tenant->refresh()->role);
        $this->assertTrue($this->property->managers()->whereKey($this->tenant->id)->exists());

        // i bývalý nájemník (vidí ho), i přes assign-manager
        $this->actingAs($this->landlord)
            ->postJson($this->apiUrl("/properties/{$this->property->id}/assign-manager"), ['user_id' => $this->formerTenant->id])
            ->assertStatus(200)
            ->assertJsonCount(3, 'managers');
        $this->assertSame(Role::Manager, $this->formerTenant->refresh()->role);
    }

    public function test_promote_needs_own_property_and_known_user(): void
    {
        $this->promote($this->landlord, $this->tenant, null)->assertStatus(422)->assertJsonValidationErrors('property_id');
        $this->promote($this->landlord, $this->tenant, $this->otherProperty->id)->assertStatus(403);
        $this->promote($this->landlord, $this->tenant, 999999)->assertStatus(404);
        $this->promote($this->landlord, User::factory()->make(['id' => 999999]), $this->property->id)->assertStatus(404);

        // #14: cizí nájemník a cizí správce (pronajímatel je nevidí), pronajímatel ani on sám
        $stranger = User::factory()->tenant()->create();
        foreach ([$stranger, $this->strangerManager, $this->otherLandlord, $this->landlord] as $user) {
            $this->promote($this->landlord, $user, $this->property->id)->assertStatus(403);
            $this->actingAs($this->landlord)
                ->postJson($this->apiUrl("/properties/{$this->property->id}/assign-manager"), ['user_id' => $user->id])
                ->assertStatus(403);
        }
        $this->assertSame(Role::Tenant, $stranger->refresh()->role);

        // jen pronajímatel
        foreach ([$this->manager, $this->tenant] as $actor) {
            $this->promote($actor, $this->formerTenant, $this->property->id)->assertStatus(403);
        }
        $this->assertSame(Role::Tenant, $this->formerTenant->refresh()->role);
    }

    public function test_manager_of_two_landlords_keeps_role_until_last_demote(): void
    {
        // druhý pronajímatel přiřadí svého nájemníka (správce prvního) jako správce
        $this->promote($this->otherLandlord, $this->manager, $this->otherProperty->id)->assertStatus(200);

        $this->demote($this->landlord, $this->manager)->assertStatus(200)->assertJsonPath('user.role', Role::Manager->value);
        $this->assertFalse($this->property->managers()->whereKey($this->manager->id)->exists());
        $this->assertTrue($this->otherProperty->managers()->whereKey($this->manager->id)->exists());
        // podruhé už u prvního pronajímatele nic nespravuje
        $this->demote($this->landlord, $this->manager)->assertStatus(403);

        $this->demote($this->otherLandlord, $this->manager)->assertStatus(200)->assertJsonPath('user.role', Role::Tenant->value);
        $this->assertSame(Role::Tenant, $this->manager->refresh()->role);

        // svou smlouvu a tikety svého bytu vidí dál
        $own = Ticket::factory()->create(['property_id' => $this->otherProperty->id, 'tenant_id' => $this->manager->id]);
        $this->actingAs($this->manager)->getJson($this->apiUrl("/leases/{$this->managerLease->id}"))->assertStatus(200);
        $this->actingAs($this->manager)->getJson($this->apiUrl("/tickets/{$own->id}"))->assertStatus(200);
    }

    public function test_demote_only_own_assignments(): void
    {
        // nájemníka, který u něj správcem není, nesesadí
        $this->demote($this->landlord, $this->tenant)->assertStatus(422);
        // cizí pronajímatel cizího správce nevidí
        $this->demote($this->otherLandlord, $this->strangerManager)->assertStatus(200);
        $this->demote($this->landlord, $this->strangerManager)->assertStatus(403);
        foreach ([$this->manager, $this->tenant] as $actor) {
            $this->demote($actor, $this->manager)->assertStatus(403);
        }
        $this->assertTrue($this->property->managers()->whereKey($this->manager->id)->exists());
    }

    public function test_remove_unassigns_tickets_and_recalculates_role(): void
    {
        $ticket = Ticket::factory()->create(['property_id' => $this->property->id, 'assigned_to' => $this->manager->id]);
        $url = $this->apiUrl("/properties/{$this->property->id}/remove-manager/{$this->manager->id}");

        $this->actingAs($this->otherLandlord)->deleteJson($url)->assertStatus(403);
        $this->actingAs($this->manager)->deleteJson($url)->assertStatus(403);

        $this->actingAs($this->landlord)->deleteJson($url)->assertStatus(200)->assertJsonCount(0, 'managers');
        $this->assertNull($ticket->refresh()->assigned_to);
        $this->assertSame(Role::Tenant, $this->manager->refresh()->role);

        // kdo přiřazený není, tomu se role nepřepočítá (404)
        $this->actingAs($this->landlord)
            ->deleteJson($this->apiUrl("/properties/{$this->property->id}/remove-manager/{$this->strangerManager->id}"))
            ->assertStatus(404);
        $this->assertSame(Role::Manager, $this->strangerManager->refresh()->role);
    }

    private function promote(User $actor, User $user, ?int $propertyId): TestResponse
    {
        return $this->actingAs($actor)->postJson(
            $this->apiUrl("/users/{$user->id}/promote-manager"),
            $propertyId === null ? [] : ['property_id' => $propertyId],
        );
    }

    private function demote(User $actor, User $user): TestResponse
    {
        return $this->actingAs($actor)->postJson($this->apiUrl("/users/{$user->id}/demote-manager"));
    }
}
