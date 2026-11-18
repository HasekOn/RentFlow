<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Měřiče a odečty: čte kdo vidí nemovitost, odečet zapisuje vlastník a přiřazený správce, zbytek jen vlastník.
 */
class MeterTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private Property $property;

    private Meter $meter;

    private MeterReading $reading;

    private User $manager;

    private User $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->landlord = User::factory()->landlord()->create();
        $this->property = Property::factory()->occupied()->create(['landlord_id' => $this->landlord->id]);
        $this->meter = Meter::factory()->create(['property_id' => $this->property->id]);
        $this->reading = MeterReading::factory()->create([
            'meter_id' => $this->meter->id,
            'reading_value' => 100,
            'reading_date' => now()->subMonth(),
            'submitted_by' => $this->landlord->id,
            'photo_proof' => 'javascript:alert(1)',
        ]);

        $this->manager = User::factory()->manager()->create();
        $this->property->managers()->attach($this->manager->id);
        $this->tenant = User::factory()->tenant()->create();
        Lease::factory()->create(['property_id' => $this->property->id, 'tenant_id' => $this->tenant->id]);
    }

    public function test_owner_manages_meters_and_readings(): void
    {
        $this->assertCanRead($this->landlord, true);

        $this->actingAs($this->landlord)
            ->postJson($this->apiUrl("/properties/{$this->property->id}/meters"), ['meter_type' => 'water'])
            ->assertStatus(201);
        $this->actingAs($this->landlord)
            ->putJson($this->apiUrl("/meters/{$this->meter->id}"), ['location' => 'Sklep'])
            ->assertStatus(200)
            ->assertJsonPath('location', 'Sklep');
        $this->assertReadingWrite($this->landlord, 201);
        $this->actingAs($this->landlord)
            ->deleteJson($this->apiUrl("/meters/{$this->meter->id}/readings/{$this->reading->id}"))
            ->assertStatus(200);
        $this->actingAs($this->landlord)->deleteJson($this->apiUrl("/meters/{$this->meter->id}"))->assertStatus(200);
        $this->assertModelMissing($this->meter);
    }

    public function test_assigned_manager_reads_and_logs_readings_only(): void
    {
        $this->assertCanRead($this->manager, true);
        $this->assertReadingWrite($this->manager, 201);
        $this->assertNoMeterWrites($this->manager);
    }

    public function test_tenant_with_active_lease_only_reads(): void
    {
        $this->assertCanRead($this->tenant, true);
        $this->assertReadingWrite($this->tenant, 403);
        $this->assertNoMeterWrites($this->tenant);
    }

    public function test_strangers_have_no_access(): void
    {
        $formerTenant = User::factory()->tenant()->create();
        Lease::factory()->ended()->create(['property_id' => $this->property->id, 'tenant_id' => $formerTenant->id]);
        $strangers = [
            User::factory()->landlord()->create(),
            User::factory()->manager()->create(),
            User::factory()->tenant()->create(),
            $formerTenant,
        ];

        foreach ($strangers as $user) {
            $this->assertCanRead($user, false);
            $this->assertReadingWrite($user, 403);
            $this->assertNoMeterWrites($user);
        }
    }

    public function test_reading_from_other_meter_in_url_returns_404(): void
    {
        $otherMeter = Meter::factory()->create(['property_id' => $this->property->id]);

        $this->actingAs($this->landlord)
            ->deleteJson($this->apiUrl("/meters/{$otherMeter->id}/readings/{$this->reading->id}"))
            ->assertStatus(404);
        $this->assertModelExists($this->reading);

        $this->actingAs($this->landlord)->getJson($this->apiUrl('/meters/99999'))->assertStatus(404);
        $this->actingAs($this->landlord)->getJson($this->apiUrl('/properties/99999/meters'))->assertStatus(404);
    }

    public function test_reading_lower_than_previous_is_rejected(): void
    {
        $this->actingAs($this->manager)
            ->postJson($this->apiUrl("/meters/{$this->meter->id}/readings"), [
                'reading_value' => 50,
                'reading_date' => now()->toDateString(),
            ])
            ->assertStatus(422);
    }

    public function test_reading_resource_does_not_return_photo_proof(): void
    {
        $this->actingAs($this->landlord)
            ->getJson($this->apiUrl("/meters/{$this->meter->id}/readings"))
            ->assertStatus(200)
            ->assertJsonPath('0.id', $this->reading->id)
            ->assertJsonMissingPath('0.photo_proof');
        $this->actingAs($this->landlord)
            ->getJson($this->apiUrl("/meters/{$this->meter->id}"))
            ->assertJsonMissingPath('readings.0.photo_proof');
    }

    public function test_meter_list_query_count_does_not_depend_on_meter_count(): void
    {
        $few = $this->countListQueries();
        foreach (Meter::factory()->count(5)->create(['property_id' => $this->property->id]) as $meter) {
            MeterReading::factory()->count(3)->create(['meter_id' => $meter->id, 'submitted_by' => $this->landlord->id]);
        }

        $this->assertSame($few, $this->countListQueries());
    }

    private function assertCanRead(User $user, bool $allowed): void
    {
        $status = $allowed ? 200 : 403;
        $this->actingAs($user)->getJson($this->apiUrl("/properties/{$this->property->id}/meters"))->assertStatus($status);
        $this->actingAs($user)->getJson($this->apiUrl("/meters/{$this->meter->id}"))->assertStatus($status);
        $this->actingAs($user)->getJson($this->apiUrl("/meters/{$this->meter->id}/readings"))->assertStatus($status);
    }

    private function assertReadingWrite(User $user, int $status): void
    {
        $this->actingAs($user)
            ->postJson($this->apiUrl("/meters/{$this->meter->id}/readings"), [
                'reading_value' => 150,
                'reading_date' => now()->toDateString(),
            ])
            ->assertStatus($status);
    }

    /**
     * Založit, upravit a smazat měřič ani smazat odečet nesmí – a nic se nezmění.
     */
    private function assertNoMeterWrites(User $user): void
    {
        $this->actingAs($user)
            ->postJson($this->apiUrl("/properties/{$this->property->id}/meters"), ['meter_type' => 'gas'])
            ->assertStatus(403);
        $this->actingAs($user)->putJson($this->apiUrl("/meters/{$this->meter->id}"), ['location' => 'X'])->assertStatus(403);
        $this->actingAs($user)->deleteJson($this->apiUrl("/meters/{$this->meter->id}"))->assertStatus(403);
        $this->actingAs($user)
            ->deleteJson($this->apiUrl("/meters/{$this->meter->id}/readings/{$this->reading->id}"))
            ->assertStatus(403);

        $this->assertModelExists($this->meter);
        $this->assertModelExists($this->reading);
        $this->assertSame(1, Meter::query()->count());
        $this->assertNotSame('X', $this->meter->refresh()->location);
    }

    private function countListQueries(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->tenant)->getJson($this->apiUrl("/properties/{$this->property->id}/meters"))->assertStatus(200);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
