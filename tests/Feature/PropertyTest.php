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

    public function test_landlord_can_list_own_properties(): void
    {
        $landlord = User::factory()->landlord()->create();
        Property::factory()->count(3)->create(['landlord_id' => $landlord->id]);
        Property::factory()->count(2)->create();

        $response = $this->actingAs($landlord)->getJson($this->apiUrl('/properties'));

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
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
        Property::factory()->count(2)->create();

        $response = $this->actingAs($tenant)->getJson($this->apiUrl('/properties'));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_unauthenticated_cannot_list_properties(): void
    {
        $response = $this->getJson($this->apiUrl('/properties'));

        $response->assertStatus(401);
    }

    public function test_landlord_can_create_property(): void
    {
        $landlord = User::factory()->landlord()->create();

        $response = $this->actingAs($landlord)->postJson($this->apiUrl('/properties'), [
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

        $response = $this->actingAs($tenant)->postJson($this->apiUrl('/properties'), [
            'address' => 'Pokus 1, Brno',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_validates_required_fields(): void
    {
        $landlord = User::factory()->landlord()->create();

        $response = $this->actingAs($landlord)->postJson($this->apiUrl('/properties'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('address');
    }

    public function test_store_validates_status_enum(): void
    {
        $landlord = User::factory()->landlord()->create();

        $response = $this->actingAs($landlord)->postJson($this->apiUrl('/properties'), [
            'address' => 'Test 1',
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_landlord_can_view_own_property(): void
    {
        $landlord = User::factory()->landlord()->create();
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);

        $response = $this->actingAs($landlord)->getJson($this->apiUrl('/properties/' . $property->id));

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

        $response = $this->actingAs($tenant)->getJson($this->apiUrl('/properties/' . $property->id));

        $response->assertStatus(200);
    }

    public function test_tenant_cannot_view_property_without_lease(): void
    {
        $tenant = User::factory()->tenant()->create();
        $property = Property::factory()->create();

        $response = $this->actingAs($tenant)->getJson($this->apiUrl('/properties/' . $property->id));

        $response->assertStatus(403);
    }

    public function test_show_returns_404_for_nonexistent_property(): void
    {
        $landlord = User::factory()->landlord()->create();

        $response = $this->actingAs($landlord)->getJson($this->apiUrl('/properties/99999'));

        $response->assertStatus(404)
            ->assertJsonPath('error', 'not_found');
    }

    public function test_landlord_can_update_own_property(): void
    {
        $landlord = User::factory()->landlord()->create();
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);

        $response = $this->actingAs($landlord)->putJson($this->apiUrl('/properties/' . $property->id), [
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

        $response = $this->actingAs($landlord)->putJson($this->apiUrl('/properties/' . $property->id), [
            'address' => 'Hacked Address',
        ]);

        $response->assertStatus(403);
    }

    public function test_tenant_cannot_update_property(): void
    {
        $tenant = User::factory()->tenant()->create();
        $property = Property::factory()->create();

        $response = $this->actingAs($tenant)->putJson($this->apiUrl('/properties/' . $property->id), [
            'address' => 'Tenant Hack',
        ]);

        $response->assertStatus(403);
    }

    public function test_landlord_can_delete_own_property(): void
    {
        $landlord = User::factory()->landlord()->create();
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);

        $response = $this->actingAs($landlord)->deleteJson($this->apiUrl('/properties/' . $property->id));

        $response->assertStatus(200);
        $this->assertSoftDeleted('properties', ['id' => $property->id]);
    }

    public function test_landlord_cannot_delete_others_property(): void
    {
        $landlord = User::factory()->landlord()->create();
        $otherLandlord = User::factory()->landlord()->create();
        $property = Property::factory()->create(['landlord_id' => $otherLandlord->id]);

        $response = $this->actingAs($landlord)->deleteJson($this->apiUrl('/properties/' . $property->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('properties', ['id' => $property->id]);
    }

    public function test_tenant_cannot_delete_property(): void
    {
        $tenant = User::factory()->tenant()->create();
        $property = Property::factory()->create();

        $response = $this->actingAs($tenant)->deleteJson($this->apiUrl('/properties/' . $property->id));

        $response->assertStatus(403);
    }

    public function test_properties_are_paginated(): void
    {
        $landlord = User::factory()->landlord()->create();
        Property::factory()->count(20)->create(['landlord_id' => $landlord->id]);

        $response = $this->actingAs($landlord)->getJson($this->apiUrl('/properties'));

        $response->assertStatus(200)
            ->assertJsonCount(15, 'data')
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_can_request_specific_page(): void
    {
        $landlord = User::factory()->landlord()->create();
        Property::factory()->count(20)->create(['landlord_id' => $landlord->id]);

        $response = $this->actingAs($landlord)->getJson($this->apiUrl('/properties?page=2'));

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }
    
    public function test_can_filter_properties_by_status(): void
    {
        $landlord = User::factory()->landlord()->create();
        Property::factory()->count(3)->create(['landlord_id' => $landlord->id, 'status' => 'occupied']);
        Property::factory()->count(2)->create(['landlord_id' => $landlord->id, 'status' => 'available']);

        $response = $this->actingAs($landlord)->getJson($this->apiUrl('/properties?status=occupied'));

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_filter_properties_by_city(): void
    {
        $landlord = User::factory()->landlord()->create();
        Property::factory()->count(2)->create(['landlord_id' => $landlord->id, 'city' => 'Praha']);
        Property::factory()->create(['landlord_id' => $landlord->id, 'city' => 'Brno']);

        $response = $this->actingAs($landlord)->getJson($this->apiUrl('/properties?city=Praha'));

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_search_properties(): void
    {
        $landlord = User::factory()->landlord()->create();
        Property::factory()->create(['landlord_id' => $landlord->id, 'address' => 'Václavské náměstí 12']);
        Property::factory()->create(['landlord_id' => $landlord->id, 'address' => 'Brněnská 45']);

        $response = $this->actingAs($landlord)->getJson($this->apiUrl('/properties?search=Václavské'));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_sort_properties_ascending(): void
    {
        $landlord = User::factory()->landlord()->create();
        Property::factory()->create(['landlord_id' => $landlord->id, 'size' => 80]);
        Property::factory()->create(['landlord_id' => $landlord->id, 'size' => 40]);
        Property::factory()->create(['landlord_id' => $landlord->id, 'size' => 120]);

        $response = $this->actingAs($landlord)->getJson($this->apiUrl('/properties?sort=size'));

        $response->assertStatus(200);
        $sizes = collect($response->json('data'))->pluck('size')->toArray();
        $this->assertEquals([40, 80, 120], $sizes);
    }

    public function test_can_sort_properties_descending(): void
    {
        $landlord = User::factory()->landlord()->create();
        Property::factory()->create(['landlord_id' => $landlord->id, 'size' => 80]);
        Property::factory()->create(['landlord_id' => $landlord->id, 'size' => 40]);
        Property::factory()->create(['landlord_id' => $landlord->id, 'size' => 120]);

        $response = $this->actingAs($landlord)->getJson($this->apiUrl('/properties?sort=-size'));

        $response->assertStatus(200);
        $sizes = collect($response->json('data'))->pluck('size')->toArray();
        $this->assertEquals([120, 80, 40], $sizes);
    }

    public function test_ignores_invalid_sort_field(): void
    {
        $landlord = User::factory()->landlord()->create();
        Property::factory()->count(2)->create(['landlord_id' => $landlord->id]);

        $response = $this->actingAs($landlord)->getJson($this->apiUrl('/properties?sort=password'));

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_combine_filter_and_sort(): void
    {
        $landlord = User::factory()->landlord()->create();
        Property::factory()->create(['landlord_id' => $landlord->id, 'status' => 'occupied', 'size' => 80]);
        Property::factory()->create(['landlord_id' => $landlord->id, 'status' => 'occupied', 'size' => 40]);
        Property::factory()->create(['landlord_id' => $landlord->id, 'status' => 'available', 'size' => 120]);

        $response = $this->actingAs($landlord)->getJson($this->apiUrl('/properties?status=occupied&sort=size'));

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
        $sizes = collect($response->json('data'))->pluck('size')->toArray();
        $this->assertEquals([40, 80], $sizes);
    }
}
