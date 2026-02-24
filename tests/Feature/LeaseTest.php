<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaseTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;
    private Property $property;

    public function test_landlord_can_list_leases(): void
    {
        $tenant = User::factory()->tenant()->create();
        Lease::factory()->count(2)->create([
            'property_id' => $this->property->id,
            'tenant_id' => $tenant->id,
        ]);

        $response = $this->actingAs($this->landlord)->getJson('/api/leases');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_tenant_sees_only_own_leases(): void
    {
        $tenant = User::factory()->tenant()->create();
        Lease::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $tenant->id,
        ]);
        Lease::factory()->create();

        $response = $this->actingAs($tenant)->getJson('/api/leases');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_landlord_can_create_lease(): void
    {
        $tenant = User::factory()->tenant()->create();

        $response = $this->actingAs($this->landlord)->postJson('/api/leases', [
            'property_id' => $this->property->id,
            'tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
            'rent_amount' => 15000,
            'deposit_amount' => 30000,
            'utility_advances' => 3000,
            'variable_symbol' => '12345',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('rent_amount', '15000.00')
            ->assertJsonPath('variable_symbol', '12345');

        $this->assertDatabaseHas('leases', [
            'property_id' => $this->property->id,
            'tenant_id' => $tenant->id,
            'variable_symbol' => '12345',
        ]);
    }

    public function test_tenant_cannot_create_lease(): void
    {
        $tenant = User::factory()->tenant()->create();

        $response = $this->actingAs($tenant)->postJson('/api/leases', [
            'property_id' => $this->property->id,
            'tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'rent_amount' => 10000,
        ]);

        $response->assertStatus(403);
    }

    public function test_landlord_cannot_create_lease_for_others_property(): void
    {
        $otherProperty = Property::factory()->create();
        $tenant = User::factory()->tenant()->create();

        $response = $this->actingAs($this->landlord)->postJson('/api/leases', [
            'property_id' => $otherProperty->id,
            'tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'rent_amount' => 10000,
        ]);

        $response->assertStatus(403);
    }

    public function test_store_validates_end_date_after_start(): void
    {
        $tenant = User::factory()->tenant()->create();

        $response = $this->actingAs($this->landlord)->postJson('/api/leases', [
            'property_id' => $this->property->id,
            'tenant_id' => $tenant->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-01-01',
            'rent_amount' => 10000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('end_date');
    }

    public function test_store_validates_unique_variable_symbol(): void
    {
        $tenant = User::factory()->tenant()->create();
        Lease::factory()->create(['variable_symbol' => '99999']);

        $response = $this->actingAs($this->landlord)->postJson('/api/leases', [
            'property_id' => $this->property->id,
            'tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'rent_amount' => 10000,
            'variable_symbol' => '99999',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('variable_symbol');
    }

    public function test_landlord_can_view_lease(): void
    {
        $lease = Lease::factory()->create([
            'property_id' => $this->property->id,
        ]);

        $response = $this->actingAs($this->landlord)->getJson('/api/leases/' . $lease->id);

        $response->assertStatus(200)
            ->assertJsonPath('id', $lease->id);
    }

    public function test_tenant_can_view_own_lease(): void
    {
        $tenant = User::factory()->tenant()->create();
        $lease = Lease::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $tenant->id,
        ]);

        $response = $this->actingAs($tenant)->getJson('/api/leases/' . $lease->id);

        $response->assertStatus(200);
    }

    public function test_tenant_cannot_view_others_lease(): void
    {
        $tenant = User::factory()->tenant()->create();
        $lease = Lease::factory()->create([
            'property_id' => $this->property->id,
        ]);

        $response = $this->actingAs($tenant)->getJson('/api/leases/' . $lease->id);

        $response->assertStatus(403);
    }

    public function test_landlord_can_update_lease(): void
    {
        $lease = Lease::factory()->create([
            'property_id' => $this->property->id,
        ]);

        $response = $this->actingAs($this->landlord)->putJson('/api/leases/' . $lease->id, [
            'rent_amount' => 18000,
            'status' => 'ended',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('rent_amount', '18000.00')
            ->assertJsonPath('status', 'ended');
    }

    public function test_tenant_cannot_update_lease(): void
    {
        $tenant = User::factory()->tenant()->create();
        $lease = Lease::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $tenant->id,
        ]);

        $response = $this->actingAs($tenant)->putJson('/api/leases/' . $lease->id, [
            'rent_amount' => 1,
        ]);

        $response->assertStatus(403);
    }

    public function test_landlord_can_delete_lease(): void
    {
        $lease = Lease::factory()->create([
            'property_id' => $this->property->id,
        ]);

        $response = $this->actingAs($this->landlord)->deleteJson('/api/leases/' . $lease->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted('leases', ['id' => $lease->id]);
    }

    public function test_other_landlord_cannot_delete_lease(): void
    {
        $otherLandlord = User::factory()->landlord()->create();
        $lease = Lease::factory()->create([
            'property_id' => $this->property->id,
        ]);

        $response = $this->actingAs($otherLandlord)->deleteJson('/api/leases/' . $lease->id);

        $response->assertStatus(403);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->landlord = User::factory()->landlord()->create();
        $this->property = Property::factory()->create(['landlord_id' => $this->landlord->id]);
    }
}
