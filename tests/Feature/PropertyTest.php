<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyTest extends TestCase
{
    use RefreshDatabase;

    // ─── Index ────────────────────────────────────

    public function test_landlord_can_list_own_properties(): void
    {
        $landlord = User::factory()->landlord()->create();
        Property::factory()->count(3)->create(['landlord_id' => $landlord->id]);
        Property::factory()->count(2)->create(); // Other landlord's properties

        $response = $this->actingAs($landlord)->getJson('/api/properties');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_tenant_sees_only_properties_with_active_lease(): void
    {
        $tenant = User::factory()->tenant()->create();
        $property = Property::factory()->create();
        Lease::factory()->create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
        ]);
        Property::factory()->count(2)->create(); // Properties without lease

        $response = $this->actingAs($tenant)->getJson('/api/properties');

        $response->assertStatus(200)
            ->assertJsonCount(1);
    }

    public function test_unauthenticated_cannot_list_properties(): void
    {
        $response = $this->getJson('/api/properties');

        $response->assertStatus(401);
    }

    // ─── Store ────────────────────────────────────

    public function test_landlord_can_create_property(): void
    {
        $landlord = User::factory()->landlord()->create();

        $response = $this->actingAs($landlord)->postJson('/api/properties', [
            'address' => 'Testovací 123, Praha',
            'city' => 'Praha',
            'zip_code' => '11000',
            'size' => 65.5,
            'disposition' => '2+kk',
            'floor' => 3,
            'status' => 'available',
            'purchase_price' => 3500000,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('address', 'Testovací 123, Praha')
            ->assertJsonPath('disposition', '2+kk');

        $this->assertDatabaseHas('properties', [
            'address' => 'Testovací 123, Praha',
            'landlord_id' => $landlord->id,
        ]);
    }

    public function test_tenant_cannot_create_property(): void
    {
        $tenant = User::factory()->tenant()->create();

        $response = $this->actingAs($tenant)->postJson('/api/properties', [
            'address' => 'Pokus 1, Brno',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_validates_required_fields(): void
    {
        $landlord = User::factory()->landlord()->create();

        $response = $this->actingAs($landlord)->postJson('/api/properties', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('address');
    }

    public function test_store_validates_status_enum(): void
    {
        $landlord = User::factory()->landlord()->create();

        $response = $this->actingAs($landlord)->postJson('/api/properties', [
            'address' => 'Test 1',
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    // ─── Show ─────────────────────────────────────

    public function test_landlord_can_view_own_property(): void
    {
        $landlord = User::factory()->landlord()->create();
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);

        $response = $this->actingAs($landlord)->getJson('/api/properties/' . $property->id);

        $response->assertStatus(200)
            ->assertJsonPath('id', $property->id)
            ->assertJsonPath('address', $property->address);
    }

    public function test_tenant_can_view_property_with_active_lease(): void
    {
        $tenant = User::factory()->tenant()->create();
        $property = Property::factory()->create();
        Lease::factory()->create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($tenant)->getJson('/api/properties/' . $property->id);

        $response->assertStatus(200);
    }

    public function test_tenant_cannot_view_property_without_lease(): void
    {
        $tenant = User::factory()->tenant()->create();
        $property = Property::factory()->create();

        $response = $this->actingAs($tenant)->getJson('/api/properties/' . $property->id);

        $response->assertStatus(403);
    }

    public function test_show_returns_404_for_nonexistent_property(): void
    {
        $landlord = User::factory()->landlord()->create();

        $response = $this->actingAs($landlord)->getJson('/api/properties/99999');

        $response->assertStatus(404)
            ->assertJsonPath('error', 'not_found');
    }

    // ─── Update ───────────────────────────────────

    public function test_landlord_can_update_own_property(): void
    {
        $landlord = User::factory()->landlord()->create();
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);

        $response = $this->actingAs($landlord)->putJson('/api/properties/' . $property->id, [
            'address' => 'Updated Address 456',
            'status' => 'renovation',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('address', 'Updated Address 456')
            ->assertJsonPath('status', 'renovation');
    }

    public function test_landlord_cannot_update_others_property(): void
    {
        $landlord = User::factory()->landlord()->create();
        $otherLandlord = User::factory()->landlord()->create();
        $property = Property::factory()->create(['landlord_id' => $otherLandlord->id]);

        $response = $this->actingAs($landlord)->putJson('/api/properties/' . $property->id, [
            'address' => 'Hacked Address',
        ]);

        $response->assertStatus(403);
    }

    public function test_tenant_cannot_update_property(): void
    {
        $tenant = User::factory()->tenant()->create();
        $property = Property::factory()->create();

        $response = $this->actingAs($tenant)->putJson('/api/properties/' . $property->id, [
            'address' => 'Tenant Hack',
        ]);

        $response->assertStatus(403);
    }

    // ─── Delete ───────────────────────────────────

    public function test_landlord_can_delete_own_property(): void
    {
        $landlord = User::factory()->landlord()->create();
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);

        $response = $this->actingAs($landlord)->deleteJson('/api/properties/' . $property->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('properties', ['id' => $property->id]);
    }

    public function test_landlord_cannot_delete_others_property(): void
    {
        $landlord = User::factory()->landlord()->create();
        $otherLandlord = User::factory()->landlord()->create();
        $property = Property::factory()->create(['landlord_id' => $otherLandlord->id]);

        $response = $this->actingAs($landlord)->deleteJson('/api/properties/' . $property->id);

        $response->assertStatus(403);
        $this->assertDatabaseHas('properties', ['id' => $property->id]);
    }

    public function test_tenant_cannot_delete_property(): void
    {
        $tenant = User::factory()->tenant()->create();
        $property = Property::factory()->create();

        $response = $this->actingAs($tenant)->deleteJson('/api/properties/' . $property->id);

        $response->assertStatus(403);
    }
}
