<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Property;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;
    private User $tenant;
    private User $manager;
    private Property $property;

    public function test_landlord_sees_tickets_for_own_properties(): void
    {
        Ticket::factory()->count(2)->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
        ]);
        Ticket::factory()->create();

        $response = $this->actingAs($this->landlord)->getJson('/api/tickets');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_tenant_sees_only_own_tickets(): void
    {
        Ticket::factory()->count(2)->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
        ]);
        Ticket::factory()->create();

        $response = $this->actingAs($this->tenant)->getJson('/api/tickets');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_manager_sees_only_assigned_tickets(): void
    {
        Ticket::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->manager->id,
        ]);
        Ticket::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->manager)->getJson('/api/tickets');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_tenant_can_create_ticket(): void
    {
        $response = $this->actingAs($this->tenant)->postJson('/api/tickets', [
            'property_id' => $this->property->id,
            'title' => 'Broken faucet',
            'description' => 'The kitchen faucet is leaking badly.',
            'category' => 'plumbing',
            'priority' => 'high',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('title', 'Broken faucet')
            ->assertJsonPath('category', 'plumbing')
            ->assertJsonPath('priority', 'high');

        $this->assertDatabaseHas('tickets', [
            'title' => 'Broken faucet',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_landlord_can_create_ticket(): void
    {
        $response = $this->actingAs($this->landlord)->postJson('/api/tickets', [
            'property_id' => $this->property->id,
            'title' => 'Schedule inspection',
            'description' => 'Annual property inspection needed.',
        ]);

        $response->assertStatus(201);
    }

    public function test_manager_cannot_create_ticket(): void
    {
        $response = $this->actingAs($this->manager)->postJson('/api/tickets', [
            'property_id' => $this->property->id,
            'title' => 'Test ticket',
            'description' => 'Manager should not be able to create.',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_validates_description_min_length(): void
    {
        $response = $this->actingAs($this->tenant)->postJson('/api/tickets', [
            'property_id' => $this->property->id,
            'title' => 'Short desc',
            'description' => 'Too short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('description');
    }

    public function test_landlord_can_view_ticket_with_comments(): void
    {
        $ticket = Ticket::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
        ]);
        TicketComment::factory()->count(2)->create([
            'ticket_id' => $ticket->id,
            'user_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->landlord)->getJson('/api/tickets/' . $ticket->id);

        $response->assertStatus(200)
            ->assertJsonPath('id', $ticket->id)
            ->assertJsonCount(2, 'comments');
    }

    public function test_other_tenant_cannot_view_ticket(): void
    {
        $otherTenant = User::factory()->tenant()->create();
        $ticket = Ticket::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($otherTenant)->getJson('/api/tickets/' . $ticket->id);

        $response->assertStatus(403);
    }

    public function test_landlord_can_update_ticket_status(): void
    {
        $ticket = Ticket::factory()->open()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->landlord)->putJson('/api/tickets/' . $ticket->id, [
            'status' => 'in_progress',
            'assigned_to' => $this->manager->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'in_progress');
    }

    public function test_assigned_manager_can_update_ticket(): void
    {
        $ticket = Ticket::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->manager->id,
        ]);

        $response = $this->actingAs($this->manager)->putJson('/api/tickets/' . $ticket->id, [
            'status' => 'resolved',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'resolved');
    }

    public function test_resolved_sets_resolved_at_automatically(): void
    {
        $ticket = Ticket::factory()->open()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($this->landlord)->putJson('/api/tickets/' . $ticket->id, [
            'status' => 'resolved',
        ]);

        $ticket->refresh();
        $this->assertNotNull($ticket->resolved_at);
    }

    public function test_tenant_cannot_update_ticket(): void
    {
        $ticket = Ticket::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->tenant)->putJson('/api/tickets/' . $ticket->id, [
            'status' => 'resolved',
        ]);

        $response->assertStatus(403);
    }

    public function test_unassigned_manager_cannot_update_ticket(): void
    {
        $ticket = Ticket::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'assigned_to' => null,
        ]);

        $response = $this->actingAs($this->manager)->putJson('/api/tickets/' . $ticket->id, [
            'status' => 'resolved',
        ]);

        $response->assertStatus(403);
    }

    public function test_landlord_can_delete_ticket(): void
    {
        $ticket = Ticket::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->landlord)->deleteJson('/api/tickets/' . $ticket->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted('tickets', ['id' => $ticket->id]);
    }

    public function test_manager_cannot_delete_ticket(): void
    {
        $ticket = Ticket::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->manager->id,
        ]);

        $response = $this->actingAs($this->manager)->deleteJson('/api/tickets/' . $ticket->id);

        $response->assertStatus(403);
    }

    public function test_user_can_list_ticket_comments(): void
    {
        $ticket = Ticket::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
        ]);
        TicketComment::factory()->count(3)->create([
            'ticket_id' => $ticket->id,
            'user_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->tenant)->getJson('/api/tickets/' . $ticket->id . '/comments');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_user_can_add_comment(): void
    {
        $ticket = Ticket::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->tenant)->postJson('/api/tickets/' . $ticket->id . '/comments', [
            'message' => 'This is urgent, please help!',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'This is urgent, please help!');
    }

    public function test_user_can_delete_own_comment(): void
    {
        $ticket = Ticket::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $comment = TicketComment::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->tenant)
            ->deleteJson('/api/tickets/' . $ticket->id . '/comments/' . $comment->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('ticket_comments', ['id' => $comment->id]);
    }

    public function test_user_cannot_delete_others_comment(): void
    {
        $ticket = Ticket::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $comment = TicketComment::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $this->landlord->id,
        ]);

        $response = $this->actingAs($this->tenant)
            ->deleteJson('/api/tickets/' . $ticket->id . '/comments/' . $comment->id);

        $response->assertStatus(403);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->landlord = User::factory()->landlord()->create();
        $this->tenant = User::factory()->tenant()->create();
        $this->manager = User::factory()->manager()->create();
        $this->property = Property::factory()->create(['landlord_id' => $this->landlord->id]);
        Lease::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);
    }
}
