<?php

namespace Tests\Feature\Actions;

use App\Actions\Leases\CreateLease;
use App\Enums\LeaseStatus;
use App\Enums\PropertyStatus;
use App\Models\Lease;
use App\Models\Property;
use App\Models\User;
use App\Notifications\LeaseExpiringNotification;
use App\Notifications\TenantInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\Feature\Concerns\PropertyRoles;
use Tests\TestCase;

/**
 * Smlouvy (#33, #53): přechody stavu na jednom místě, pozvánka po commitu, upozornění na konec jen 30 a 7 dní předem.
 */
class LeaseRulesTest extends TestCase
{
    use PropertyRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->createPropertyRoles();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_reactivation_occupies_the_property_again(): void
    {
        $property = Property::factory()->create(['landlord_id' => $this->landlord->id, 'status' => PropertyStatus::Available]);
        $lease = Lease::factory()->ended()->create([
            'property_id' => $property->id, 'start_date' => '2026-01-01', 'end_date' => '2027-12-31',
        ]);

        $this->actingAs($this->landlord)
            ->putJson($this->apiUrl("/leases/{$lease->id}"), ['status' => LeaseStatus::Active->value])
            ->assertStatus(200);

        $this->assertSame(PropertyStatus::Occupied, $property->refresh()->status);
    }

    public function test_reactivation_under_renovation_is_rejected(): void
    {
        $property = Property::factory()->create(['landlord_id' => $this->landlord->id, 'status' => PropertyStatus::Renovation]);
        $lease = Lease::factory()->ended()->create(['property_id' => $property->id]);

        $this->actingAs($this->landlord)
            ->putJson($this->apiUrl("/leases/{$lease->id}"), ['status' => LeaseStatus::Active->value])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertSame(LeaseStatus::Ended, $lease->refresh()->status);
    }

    public function test_tenant_invitation_is_sent_only_after_commit(): void
    {
        $property = Property::factory()->create(['landlord_id' => $this->landlord->id, 'status' => PropertyStatus::Available]);
        $tenant = User::factory()->tenant()->create();
        $data = ['tenant_id' => $tenant->id, 'start_date' => '2027-01-01', 'end_date' => null, 'rent_amount' => 10000];

        try {
            DB::transaction(function () use ($property, $data): void {
                app(CreateLease::class)->handle($property, $data);

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('leases', ['tenant_id' => $tenant->id]);
        $this->assertSame(PropertyStatus::Available, $property->refresh()->status);

        app(CreateLease::class)->handle($property, $data);
        Notification::assertSentToTimes($tenant, TenantInvitationNotification::class, 1);
        $this->assertSame(PropertyStatus::Occupied, $property->refresh()->status);
    }

    public function test_expiring_lease_is_announced_30_and_7_days_before_only(): void
    {
        $this->lease->update(['end_date' => '2026-12-31']);

        foreach (Carbon::parse('2026-11-25')->daysUntil('2026-12-30') as $day) {
            Carbon::setTestNow($day->copy()->setTime(6, 0));
            $this->artisan('rentflow:check-expiring-leases')->assertSuccessful();
        }

        // 1. 12. (30 dní předem) a 24. 12. (7 dní předem), ne každý den
        Notification::assertSentToTimes($this->tenant, LeaseExpiringNotification::class, 2);
        Notification::assertSentToTimes($this->landlord, LeaseExpiringNotification::class, 2);
    }
}
