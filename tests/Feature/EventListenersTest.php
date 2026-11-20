<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Events\LeaseCreated;
use App\Events\PaymentMarkedPaid;
use App\Events\TicketCreated;
use App\Events\TicketResolved;
use App\Listeners\RecalculateTrustScore;
use App\Listeners\SendTenantInvitation;
use App\Listeners\SendTicketCreatedNotification;
use App\Listeners\SendTicketResolvedNotification;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TenantInvitationNotification;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketResolvedNotification;
use App\Services\TrustScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use Tests\TestCase;

class EventListenersTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->landlord = User::factory()->landlord()->create();
        $this->tenant = User::factory()->tenant()->create();
        $this->property = Property::factory()->occupied()->create(['landlord_id' => $this->landlord->id]);
    }

    public function test_each_listener_is_registered_exactly_once(): void
    {
        $expected = [
            LeaseCreated::class => [SendTenantInvitation::class],
            TicketCreated::class => [SendTicketCreatedNotification::class],
            TicketResolved::class => [SendTicketResolvedNotification::class],
            PaymentMarkedPaid::class => [RecalculateTrustScore::class],
        ];

        foreach ($expected as $event => $listeners) {
            // Listener z $listen je „Třída“, z auto-discovery „Třída@handle“ – obojí je stejný listener.
            $registered = array_map(
                fn ($listener) => is_string($listener) ? explode('@', $listener)[0] : $listener,
                Event::getRawListeners()[$event] ?? [],
            );
            sort($registered);

            $this->assertSame($listeners, $registered, $event);
        }
    }

    public function test_ticket_created_notifies_landlord_once(): void
    {
        Lease::factory()->create(['property_id' => $this->property->id, 'tenant_id' => $this->tenant->id]);

        $this->actingAs($this->tenant)->postJson($this->apiUrl('/tickets'), [
            'property_id' => $this->property->id,
            'title' => 'Kape kohoutek',
            'description' => 'V kuchyni kape kohoutek i po zavření.',
        ])->assertStatus(201);

        Notification::assertSentToTimes($this->landlord, TicketCreatedNotification::class, 1);
    }

    public function test_ticket_resolved_notifies_tenant_once(): void
    {
        // stav pevně: notifikace jde jen při přechodu na vyřešený (#54), factory dává náhodný stav
        $ticket = Ticket::factory()->create([
            'property_id' => $this->property->id, 'tenant_id' => $this->tenant->id, 'status' => TicketStatus::InProgress,
        ]);

        $this->actingAs($this->landlord)
            ->putJson($this->apiUrl("/tickets/{$ticket->id}"), ['status' => 'resolved'])
            ->assertStatus(200);

        Notification::assertSentToTimes($this->tenant, TicketResolvedNotification::class, 1);
    }

    public function test_lease_created_invites_tenant_once(): void
    {
        $property = Property::factory()->available()->create(['landlord_id' => $this->landlord->id]);

        $this->actingAs($this->landlord)->postJson($this->apiUrl('/leases'), [
            'property_id' => $property->id,
            'tenant_email' => $this->tenant->email,
            'start_date' => '2026-01-01',
            'rent_amount' => 12000,
        ])->assertStatus(201);

        Notification::assertSentToTimes($this->tenant, TenantInvitationNotification::class, 1);
    }

    public function test_payment_marked_paid_recalculates_trust_score_once(): void
    {
        $lease = Lease::factory()->create(['property_id' => $this->property->id, 'tenant_id' => $this->tenant->id]);
        $payment = Payment::factory()->unpaid()->create(['lease_id' => $lease->id]);

        $this->partialMock(TrustScoreService::class, function (MockInterface $mock) {
            $mock->shouldReceive('calculate')->once()->passthru();
        });

        $this->actingAs($this->landlord)
            ->putJson($this->apiUrl("/payments/{$payment->id}/mark-paid"))
            ->assertStatus(200)
            ->assertJsonPath('status', 'paid');

        // potvrzení platby nájemníkovi zatím není (prázdný listener smazán, #56)
        Notification::assertNothingSent();
    }
}
