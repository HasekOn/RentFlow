<?php

namespace Tests\Feature\Actions;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketResolvedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\PropertyRoles;
use Tests\TestCase;

/**
 * Tikety (#47, #54): kdo dostane upozornění na nový tiket, vyřešení jen při skutečném přechodu stavu.
 */
class TicketRulesTest extends TestCase
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

    public function test_new_ticket_is_announced_to_owner_and_assigned_managers_not_to_author(): void
    {
        $this->actingAs($this->tenant)->postJson($this->apiUrl('/tickets'), $this->payload())->assertStatus(201);

        Notification::assertSentToTimes($this->landlord, TicketCreatedNotification::class, 1);
        Notification::assertSentToTimes($this->manager, TicketCreatedNotification::class, 1);
        Notification::assertNotSentTo($this->tenant, TicketCreatedNotification::class);
        Notification::assertNotSentTo($this->strangerManager, TicketCreatedNotification::class);

        // správce zakládá tiket ve spravovaném bytě: upozornění jen vlastníkovi
        $this->actingAs($this->manager)->postJson($this->apiUrl('/tickets'), $this->payload())->assertStatus(201);
        Notification::assertSentToTimes($this->manager, TicketCreatedNotification::class, 1);
        Notification::assertSentToTimes($this->landlord, TicketCreatedNotification::class, 2);
    }

    public function test_resolving_twice_notifies_once_and_reopening_clears_resolved_at(): void
    {
        $ticket = Ticket::factory()->create([
            'property_id' => $this->property->id, 'tenant_id' => $this->tenant->id, 'status' => TicketStatus::InProgress,
        ]);

        Carbon::setTestNow('2026-11-19 10:00');
        $this->resolve($ticket, TicketStatus::Resolved);
        Carbon::setTestNow('2026-11-20 10:00');
        $this->resolve($ticket, TicketStatus::Resolved);

        Notification::assertSentToTimes($this->tenant, TicketResolvedNotification::class, 1);
        $this->assertSame('2026-11-19 10:00:00', $ticket->refresh()->resolved_at?->format('Y-m-d H:i:s'));

        $this->resolve($ticket, TicketStatus::InProgress);
        $this->assertNull($ticket->refresh()->resolved_at);
    }

    private function resolve(Ticket $ticket, TicketStatus $status): void
    {
        $this->actingAs($this->landlord)
            ->putJson($this->apiUrl("/tickets/{$ticket->id}"), ['status' => $status->value])
            ->assertStatus(200);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return ['property_id' => $this->property->id, 'title' => 'Kape kohoutek', 'description' => 'V koupelně kape kohoutek u umyvadla.'];
    }
}
