<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;
    private User $tenant;
    private Property $property;
    private Lease $lease;

    public function test_landlord_can_list_payments(): void
    {
        Payment::factory()->count(3)->create(['lease_id' => $this->lease->id]);

        $response = $this->actingAs($this->landlord)->getJson($this->apiUrl('/payments'));

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_tenant_sees_only_own_payments(): void
    {
        Payment::factory()->count(2)->create(['lease_id' => $this->lease->id]);
        Payment::factory()->count(3)->create();

        $response = $this->actingAs($this->tenant)->getJson($this->apiUrl('/payments'));

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_landlord_can_create_payment(): void
    {
        $response = $this->actingAs($this->landlord)->postJson($this->apiUrl('/payments'), [
            'lease_id' => $this->lease->id,
            'type' => 'rent',
            'amount' => 15000,
            'due_date' => '2026-03-15',
            'variable_symbol' => '12345',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('type', 'rent')
            ->assertJsonPath('status', 'unpaid');
    }

    public function test_tenant_cannot_create_payment(): void
    {
        $response = $this->actingAs($this->tenant)->postJson($this->apiUrl('/payments'), [
            'lease_id' => $this->lease->id,
            'type' => 'rent',
            'amount' => 15000,
            'due_date' => '2026-03-15',
        ]);

        $response->assertStatus(403);
    }

    public function test_auto_sets_paid_status_when_paid_date_provided(): void
    {
        $response = $this->actingAs($this->landlord)->postJson($this->apiUrl('/payments'), [
            'lease_id' => $this->lease->id,
            'type' => 'rent',
            'amount' => 15000,
            'due_date' => '2026-03-15',
            'paid_date' => '2026-03-14',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'paid');
    }

    public function test_store_validates_type_enum(): void
    {
        $response = $this->actingAs($this->landlord)->postJson($this->apiUrl('/payments'), [
            'lease_id' => $this->lease->id,
            'type' => 'invalid',
            'amount' => 15000,
            'due_date' => '2026-03-15',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_landlord_can_view_payment(): void
    {
        $payment = Payment::factory()->create(['lease_id' => $this->lease->id]);

        $response = $this->actingAs($this->landlord)->getJson($this->apiUrl('/payments/' . $payment->id));

        $response->assertStatus(200)
            ->assertJsonPath('id', $payment->id);
    }

    public function test_tenant_can_view_own_payment(): void
    {
        $payment = Payment::factory()->create(['lease_id' => $this->lease->id]);

        $response = $this->actingAs($this->tenant)->getJson($this->apiUrl('/payments/' . $payment->id));

        $response->assertStatus(200);
    }

    public function test_other_tenant_cannot_view_payment(): void
    {
        $otherTenant = User::factory()->tenant()->create();
        $payment = Payment::factory()->create(['lease_id' => $this->lease->id]);

        $response = $this->actingAs($otherTenant)->getJson($this->apiUrl('/payments/' . $payment->id));

        $response->assertStatus(403);
    }

    public function test_landlord_can_update_payment(): void
    {
        $payment = Payment::factory()->create(['lease_id' => $this->lease->id]);

        $response = $this->actingAs($this->landlord)->putJson($this->apiUrl('/payments/' . $payment->id), [
            'note' => 'Updated note',
            'status' => 'paid',
            'paid_date' => '2026-03-10',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('note', 'Updated note')
            ->assertJsonPath('status', 'paid');
    }

    public function test_tenant_cannot_update_payment(): void
    {
        $payment = Payment::factory()->create(['lease_id' => $this->lease->id]);

        $response = $this->actingAs($this->tenant)->putJson($this->apiUrl('/payments/' . $payment->id), [
            'status' => 'paid',
        ]);

        $response->assertStatus(403);
    }

    public function test_landlord_can_mark_payment_as_paid(): void
    {
        $payment = Payment::factory()->unpaid()->create(['lease_id' => $this->lease->id]);

        $response = $this->actingAs($this->landlord)->putJson($this->apiUrl('/payments/' . $payment->id . '/mark-paid'));

        $response->assertStatus(200)
            ->assertJsonPath('status', 'paid');

        $this->assertNotNull($payment->fresh()->paid_date);
    }

    public function test_mark_paid_recalculates_trust_score(): void
    {
        $payment = Payment::factory()->unpaid()->create([
            'lease_id' => $this->lease->id,
            'due_date' => now()->subDays(3),
        ]);

        $oldScore = $this->tenant->trust_score;

        $this->actingAs($this->landlord)->putJson($this->apiUrl('/payments/' . $payment->id . '/mark-paid'));

        $this->tenant->refresh();
        $this->assertNotEquals($oldScore, $this->tenant->trust_score);
    }

    public function test_landlord_can_delete_payment(): void
    {
        $payment = Payment::factory()->create(['lease_id' => $this->lease->id]);

        $response = $this->actingAs($this->landlord)->deleteJson($this->apiUrl('/payments/' . $payment->id));

        $response->assertStatus(200);
        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
    }

    public function test_landlord_can_import_csv(): void
    {
        // Create unpaid payment with matching variable symbol
        Payment::factory()->unpaid()->create([
            'lease_id' => $this->lease->id,
            'variable_symbol' => '12345',
            'amount' => 15000,
            'due_date' => '2026-03-15',
        ]);

        $csvContent = "Datum;Částka;Variabilní symbol\n15.03.2026;15000,00;12345";
        $file = UploadedFile::fake()->createWithContent('bank_export.csv', $csvContent);

        $response = $this->actingAs($this->landlord)->postJson($this->apiUrl('/payments/import-csv'), [
            'file' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('summary.total_rows', 1)
            ->assertJsonPath('summary.matched', 1);
    }

    public function test_csv_import_handles_unmatched_symbols(): void
    {
        $csvContent = "Datum;Částka;Variabilní symbol\n15.03.2026;15000,00;99999";
        $file = UploadedFile::fake()->createWithContent('bank_export.csv', $csvContent);

        $response = $this->actingAs($this->landlord)->postJson($this->apiUrl('/payments/import-csv'), [
            'file' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('summary.matched', 0)
            ->assertJsonPath('summary.unmatched', 1);
    }

    public function test_tenant_cannot_import_csv(): void
    {
        $csvContent = "Datum;Částka;Variabilní symbol\n15.03.2026;15000;12345";
        $file = UploadedFile::fake()->createWithContent('bank_export.csv', $csvContent);

        $response = $this->actingAs($this->tenant)->postJson($this->apiUrl('/payments/import-csv'), [
            'file' => $file,
        ]);

        $response->assertStatus(403);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->landlord = User::factory()->landlord()->create();
        $this->tenant = User::factory()->tenant()->create();
        $this->property = Property::factory()->create(['landlord_id' => $this->landlord->id]);
        $this->lease = Lease::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'variable_symbol' => '12345',
        ]);
    }
}
