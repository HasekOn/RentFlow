<?php

namespace Tests\Feature\Actions;

use App\Actions\Payments\GenerateMonthlyPayments;
use App\Enums\LeaseStatus;
use App\Enums\PropertyStatus;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\PropertyRoles;
use Tests\TestCase;

/**
 * S1 + S2: nová smlouva je pozvánka. Kontakty, hodnocení a Trust Score nájemníka jen přes smlouvu, kterou nájemník
 * přijal; platby a hodnocení na pozvánce nebo odmítnuté smlouvě skóre neovlivní.
 */
class LeaseAcceptanceTest extends TestCase
{
    use PropertyRoles, RefreshDatabase;

    private Property $freeProperty;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->createPropertyRoles();
        $this->freeProperty = Property::factory()->available()->create(['landlord_id' => $this->landlord->id]);
    }

    public function test_tenant_accepts_invitation_and_property_becomes_occupied(): void
    {
        $lease = $this->invite($this->landlord, $this->freeProperty, $this->formerTenant);

        $this->assertSame(LeaseStatus::Pending, $lease->status);
        $this->assertNull($lease->accepted_at);
        $this->assertSame(PropertyStatus::Available, $this->freeProperty->refresh()->status);

        $this->actingAs($this->formerTenant)
            ->putJson($this->apiUrl("/leases/{$lease->id}/accept"))
            ->assertStatus(200)
            ->assertJsonPath('status', 'active');

        $this->assertNotNull($lease->refresh()->accepted_at);
        $this->assertSame(PropertyStatus::Occupied, $this->freeProperty->refresh()->status);
    }

    public function test_only_the_invited_tenant_can_respond(): void
    {
        $lease = $this->invite($this->landlord, $this->freeProperty, $this->formerTenant);

        foreach ([$this->landlord, $this->otherLandlord, $this->manager, $this->tenant] as $user) {
            $this->actingAs($user)->putJson($this->apiUrl("/leases/{$lease->id}/accept"))->assertStatus(403);
            $this->actingAs($user)->putJson($this->apiUrl("/leases/{$lease->id}/decline"))->assertStatus(403);
        }

        $this->assertSame(LeaseStatus::Pending, $lease->refresh()->status);
    }

    public function test_declined_invitation_cannot_be_accepted_or_changed(): void
    {
        $lease = $this->invite($this->landlord, $this->freeProperty, $this->formerTenant);

        $this->actingAs($this->formerTenant)
            ->putJson($this->apiUrl("/leases/{$lease->id}/decline"))
            ->assertStatus(200)
            ->assertJsonPath('status', 'declined');

        $this->actingAs($this->formerTenant)
            ->putJson($this->apiUrl("/leases/{$lease->id}/accept"))
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
        $this->actingAs($this->landlord)
            ->putJson($this->apiUrl("/leases/{$lease->id}"), ['rent_amount' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertSame(LeaseStatus::Declined, $lease->refresh()->status);
        $this->assertNull($lease->accepted_at);
    }

    public function test_accepting_twice_fails(): void
    {
        $lease = $this->invite($this->landlord, $this->freeProperty, $this->formerTenant);

        $this->actingAs($this->formerTenant)->putJson($this->apiUrl("/leases/{$lease->id}/accept"))->assertStatus(200);
        $this->actingAs($this->formerTenant)
            ->putJson($this->apiUrl("/leases/{$lease->id}/accept"))
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_accept_checks_renovation_and_end_date(): void
    {
        $lease = $this->invite($this->landlord, $this->freeProperty, $this->formerTenant);
        $this->freeProperty->update(['status' => PropertyStatus::Renovation]);

        $this->actingAs($this->formerTenant)
            ->putJson($this->apiUrl("/leases/{$lease->id}/accept"))
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->freeProperty->update(['status' => PropertyStatus::Available]);
        $lease->update(['start_date' => now()->subYear(), 'end_date' => now()->subDay()]);

        $this->actingAs($this->formerTenant)
            ->putJson($this->apiUrl("/leases/{$lease->id}/accept"))
            ->assertStatus(422)
            ->assertJsonValidationErrors('end_date');
        $this->assertSame(LeaseStatus::Pending, $lease->refresh()->status);
    }

    public function test_landlord_cannot_activate_invitation_but_can_withdraw_it(): void
    {
        $lease = $this->invite($this->landlord, $this->freeProperty, $this->formerTenant);

        foreach ([LeaseStatus::Active, LeaseStatus::Ended] as $status) {
            $this->actingAs($this->landlord)
                ->putJson($this->apiUrl("/leases/{$lease->id}"), ['status' => $status->value])
                ->assertStatus(422)
                ->assertJsonValidationErrors('status');
        }
        foreach ([LeaseStatus::Pending, LeaseStatus::Declined] as $status) {
            $this->actingAs($this->landlord)
                ->putJson($this->apiUrl("/leases/{$lease->id}"), ['status' => $status->value])
                ->assertStatus(422)
                ->assertJsonValidationErrors('status');
        }
        $this->assertSame(LeaseStatus::Pending, $lease->refresh()->status);

        $this->actingAs($this->landlord)
            ->putJson($this->apiUrl("/leases/{$lease->id}"), ['status' => LeaseStatus::Terminated->value])
            ->assertStatus(200);
        $this->assertSame(LeaseStatus::Terminated, $lease->refresh()->status);

        // odvolanou pozvánku nejde později reaktivovat, nikdy nebyla přijatá
        $this->actingAs($this->landlord)
            ->putJson($this->apiUrl("/leases/{$lease->id}"), ['status' => LeaseStatus::Active->value])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_duplicate_invitation_is_rejected(): void
    {
        $this->invite($this->landlord, $this->freeProperty, $this->formerTenant);

        $this->actingAs($this->landlord)
            ->postJson($this->apiUrl('/leases'), $this->payload($this->freeProperty, $this->formerTenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors('tenant_email');
    }

    public function test_foreign_landlord_with_invitation_sees_no_contacts_ratings_or_score(): void
    {
        // oběť má přijatou smlouvu u pronajímatele, cizí pronajímatel jí pošle pozvánku
        $victim = $this->tenant;
        Rating::factory()->create(['lease_id' => $this->lease->id, 'rated_by' => $this->landlord->id, 'comment' => 'Tajné']);
        $attackerProperty = Property::factory()->available()->create(['landlord_id' => $this->otherLandlord->id]);
        $invitation = $this->invite($this->otherLandlord, $attackerProperty, $victim);

        $this->actingAs($this->otherLandlord);
        $this->assertNotContains($victim->id, array_column($this->getJson($this->apiUrl('/users'))->assertStatus(200)->json(), 'id'));
        $tenantJson = $this->getJson($this->apiUrl("/leases/{$invitation->id}"))->assertStatus(200)->json('tenant');
        $this->assertSame($victim->id, $tenantJson['id']);
        $this->assertArrayNotHasKey('email', $tenantJson);
        $this->assertArrayNotHasKey('phone', $tenantJson);
        $this->assertArrayNotHasKey('trust_score', $tenantJson);
        $this->getJson($this->apiUrl("/users/{$victim->id}/ratings"))->assertStatus(403);
        $this->getJson($this->apiUrl("/tenants/{$victim->id}/trust-score"))->assertStatus(403);

        // kontrola: po přijetí nájemníkem kontakty vidí
        $this->actingAs($victim)->putJson($this->apiUrl("/leases/{$invitation->id}/accept"))->assertStatus(200);
        $this->actingAs($this->otherLandlord)->getJson($this->apiUrl("/users/{$victim->id}/ratings"))->assertStatus(200);
    }

    public function test_fake_payments_and_ratings_on_unaccepted_leases_do_not_change_trust_score(): void
    {
        $victim = $this->tenant;
        Payment::factory()->paid()->create(['lease_id' => $this->lease->id]);
        $before = $victim->recalculateTrustScore();

        $attackerProperty = Property::factory()->available()->create(['landlord_id' => $this->otherLandlord->id]);
        $pending = $this->invite($this->otherLandlord, $attackerProperty, $victim);
        $declined = Lease::factory()->declined()->create(['property_id' => $attackerProperty->id, 'tenant_id' => $victim->id]);

        foreach ([$pending, $declined] as $lease) {
            $this->actingAs($this->otherLandlord)
                ->postJson($this->apiUrl('/payments'), [
                    'lease_id' => $lease->id, 'type' => 'rent', 'amount' => 1000, 'due_date' => now()->subMonths(3)->toDateString(),
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors('lease_id');
            $this->actingAs($this->otherLandlord)
                ->postJson($this->apiUrl("/leases/{$lease->id}/ratings"), ['category' => 'overall', 'score' => 1])
                ->assertStatus(422)
                ->assertJsonValidationErrors('lease');

            // i data, která by vznikla mimo API, skóre neovlivní
            Payment::factory()->unpaid()->count(6)->create(['lease_id' => $lease->id, 'type' => 'rent', 'due_date' => now()->subMonths(6)]);
            Rating::factory()->create(['lease_id' => $lease->id, 'rated_by' => $this->otherLandlord->id, 'score' => 1]);
        }

        $this->assertSame($before, $victim->recalculateTrustScore());
        $this->assertSame(1, $this->actingAs($this->landlord)->getJson($this->apiUrl("/tenants/{$victim->id}/trust-score"))
            ->assertStatus(200)
            ->json('breakdown.total_payments'));
    }

    public function test_monthly_payments_skip_invitations(): void
    {
        $lease = $this->invite($this->landlord, $this->freeProperty, $this->formerTenant, now()->startOfMonth()->subMonth());

        app(GenerateMonthlyPayments::class)->handle($this->landlord, now()->startOfMonth());

        $this->assertSame(0, Payment::query()->where('lease_id', $lease->id)->count());
        $this->assertGreaterThan(0, Payment::query()->where('lease_id', $this->lease->id)->count());
    }

    public function test_migration_marks_existing_leases_as_accepted_at_creation(): void
    {
        $migration = require database_path('migrations/2026_11_20_000001_add_accepted_at_to_leases.php');
        $migration->down();
        DB::table('leases')->where('id', $this->lease->id)->update(['created_at' => '2025-03-04 10:11:12']);

        $migration->up();

        $this->assertSame('2025-03-04 10:11:12', DB::table('leases')->where('id', $this->lease->id)->value('accepted_at'));
        $this->assertSame(0, DB::table('leases')->whereNull('accepted_at')->count());
    }

    private function invite(User $landlord, Property $property, User $tenant, ?Carbon $start = null): Lease
    {
        $id = $this->actingAs($landlord)
            ->postJson($this->apiUrl('/leases'), $this->payload($property, $tenant, $start))
            ->assertStatus(201)
            ->json('id');

        $lease = Lease::query()->find($id);
        $this->assertNotNull($lease);

        return $lease;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Property $property, User $tenant, ?Carbon $start = null): array
    {
        return [
            'property_id' => $property->id,
            'tenant_email' => $tenant->email,
            'start_date' => ($start ?? now()->addMonth()->startOfMonth())->toDateString(),
            'rent_amount' => 12000,
        ];
    }
}
