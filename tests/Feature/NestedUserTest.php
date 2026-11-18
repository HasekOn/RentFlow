<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Property;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Vnoření uživatelé: e-mail, telefon a trust score jen pro toho, kdo osobu vidí (UserPolicy::view), jinak souhrn.
 */
class NestedUserTest extends TestCase
{
    use RefreshDatabase;

    private const array PRIVATE_FIELDS = ['email', 'phone', 'trust_score'];

    private User $landlord;

    private User $tenant;

    private User $manager;

    private Property $property;

    private Lease $lease;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->landlord = User::factory()->landlord()->create();
        $this->property = Property::factory()->occupied()->create(['landlord_id' => $this->landlord->id]);
        $this->tenant = User::factory()->tenant()->create();
        $this->lease = Lease::factory()->create(['property_id' => $this->property->id, 'tenant_id' => $this->tenant->id]);
        $this->manager = User::factory()->manager()->create();
        $this->property->managers()->attach($this->manager->id);

        $this->ticket = Ticket::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->manager->id,
        ]);
        foreach ([$this->landlord, $this->manager, $this->tenant] as $author) {
            TicketComment::factory()->create(['ticket_id' => $this->ticket->id, 'user_id' => $author->id]);
        }
    }

    public function test_tenant_sees_only_summary_of_other_side_in_ticket_and_comments(): void
    {
        $response = $this->actingAs($this->tenant)->getJson($this->apiUrl("/tickets/{$this->ticket->id}"))->assertStatus(200);

        $this->assertFullUser($response, 'tenant');
        $this->assertSummaryUser($response, 'assigned_user');
        $this->assertSummaryUser($response, $this->commentPath($response, 'comments', $this->landlord));
        $this->assertSummaryUser($response, $this->commentPath($response, 'comments', $this->manager));
        $this->assertFullUser($response, $this->commentPath($response, 'comments', $this->tenant));

        $comments = $this->actingAs($this->tenant)
            ->getJson($this->apiUrl("/tickets/{$this->ticket->id}/comments"))
            ->assertStatus(200);
        $this->assertSummaryUser($comments, $this->commentPath($comments, null, $this->landlord));
        $this->assertSummaryUser($comments, $this->commentPath($comments, null, $this->manager));
    }

    public function test_landlord_and_manager_see_full_profile_of_people_they_can_view(): void
    {
        $response = $this->actingAs($this->landlord)->getJson($this->apiUrl("/tickets/{$this->ticket->id}"))->assertStatus(200);
        $this->assertFullUser($response, 'tenant');
        $this->assertFullUser($response, 'assigned_user');

        // správce vidí nájemníka aktivní smlouvy, pronajímatele ne (není v jeho User::visibleTo)
        $response = $this->actingAs($this->manager)->getJson($this->apiUrl("/tickets/{$this->ticket->id}"))->assertStatus(200);
        $this->assertFullUser($response, 'tenant');
        $this->assertSummaryUser($response, $this->commentPath($response, 'comments', $this->landlord));
    }

    public function test_lease_and_property_hide_contacts_of_landlord_from_tenant(): void
    {
        $this->assertFullUser($this->actingAs($this->tenant)->getJson($this->apiUrl("/leases/{$this->lease->id}")), 'tenant');
        $this->assertFullUser($this->actingAs($this->landlord)->getJson($this->apiUrl("/leases/{$this->lease->id}")), 'tenant');

        $property = $this->actingAs($this->tenant)->getJson($this->apiUrl("/properties/{$this->property->id}"))->assertStatus(200);
        $this->assertSummaryUser($property, 'landlord');
        $this->assertFullUser($property, 'leases.0.tenant');
    }

    public function test_query_count_does_not_depend_on_number_of_comment_authors(): void
    {
        $few = $this->countTicketQueries();
        foreach (User::factory()->tenant()->count(5)->create() as $author) {
            TicketComment::factory()->create(['ticket_id' => $this->ticket->id, 'user_id' => $author->id]);
        }

        $this->assertSame($few, $this->countTicketQueries());
    }

    /**
     * Cesta k uživateli komentáře podle autora (komentáře mají stejný created_at, pořadí není zaručené).
     */
    private function commentPath(TestResponse $response, ?string $list, User $author): string
    {
        $comments = $list === null ? $response->json() : $response->json($list);
        $index = array_search($author->id, array_column(array_column($comments, 'user'), 'id'), true);
        $this->assertIsInt($index, "komentář uživatele {$author->id} chybí");

        return ($list === null ? '' : $list.'.').$index.'.user';
    }

    private function assertFullUser(TestResponse $response, string $path): void
    {
        $response->assertJsonPath($path.'.name', $response->json($path.'.name'));
        foreach (self::PRIVATE_FIELDS as $field) {
            $this->assertArrayHasKey($field, $response->json($path), "{$path}.{$field} chybí");
        }
    }

    private function assertSummaryUser(TestResponse $response, string $path): void
    {
        $this->assertSame(['id', 'name', 'role'], array_keys($response->json($path)), $path);
    }

    private function countTicketQueries(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->tenant)->getJson($this->apiUrl("/tickets/{$this->ticket->id}"))->assertStatus(200);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
