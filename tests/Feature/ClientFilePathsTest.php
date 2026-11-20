<?php

namespace Tests\Feature;

use App\Models\Meter;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cesty k souborům nastavuje jen server (#43), klient je nesmí podstrčit.
 */
class ClientFilePathsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_ignores_invoice_path_from_client(): void
    {
        $landlord = User::factory()->landlord()->create();
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);

        $response = $this->actingAs($landlord)->postJson($this->apiUrl('/expenses'), [
            'property_id' => $property->id,
            'type' => 'repair',
            'amount' => 1500,
            'expense_date' => '2026-03-01',
            'invoice_path' => '../../.env',
        ])->assertStatus(201);

        $this->assertDatabaseHas('expenses', ['id' => $response->json('id'), 'invoice_path' => null]);

        $this->actingAs($landlord)
            ->putJson($this->apiUrl('/expenses/'.$response->json('id')), ['invoice_path' => 'https://evil.example/x.html'])
            ->assertStatus(200);

        $this->assertDatabaseHas('expenses', ['id' => $response->json('id'), 'invoice_path' => null]);
    }

    public function test_meter_reading_ignores_photo_proof_from_client(): void
    {
        $landlord = User::factory()->landlord()->create();
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);
        $meter = Meter::factory()->create(['property_id' => $property->id]);

        $response = $this->actingAs($landlord)->postJson($this->apiUrl("/meters/{$meter->id}/readings"), [
            'reading_value' => 120,
            'reading_date' => '2026-03-01',
            'photo_proof' => 'https://evil.example/x.html',
        ])->assertStatus(201);

        $this->assertDatabaseHas('meter_readings', ['id' => $response->json('id'), 'photo_proof' => null]);
    }
}
