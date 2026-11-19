<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Property;
use App\Models\Ticket;
use App\Models\TicketImage;
use App\Models\User;
use App\Support\PrivateFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Concerns\PropertyRoles;
use Tests\TestCase;

/**
 * Tikety (2.10): kdo je vidí (Ticket::visibleTo), zakládá (#11, #24), upravuje (#46), přiřazuje řešitele (#23),
 * komentuje a nahrává fotky (#10). Správce je zároveň nájemníkem cizí nemovitosti ($managerLease).
 */
class TicketAccessTest extends TestCase
{
    use PropertyRoles, RefreshDatabase;

    private Ticket $ticket;

    private Ticket $formerTicket;

    private Ticket $managerTicket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPropertyRoles();
        Storage::fake(PrivateFile::DISK);

        $new = ['status' => TicketStatus::New];
        $this->ticket = Ticket::factory()->create(['property_id' => $this->property->id, 'tenant_id' => $this->tenant->id, ...$new]);
        $this->formerTicket = Ticket::factory()->create(['property_id' => $this->property->id, 'tenant_id' => $this->formerTenant->id]);
        // správce hlásí závadu ve svém bytě u cizího pronajímatele
        $this->managerTicket = Ticket::factory()->create(['property_id' => $this->otherProperty->id, 'tenant_id' => $this->manager->id, ...$new]);
    }

    public function test_list_and_detail_by_relation_to_property(): void
    {
        $this->assertListed($this->landlord, [$this->ticket, $this->formerTicket]);
        // nájemník s aktivní smlouvou vidí všechny tikety bytu (rozhodnutí při plánu)
        $this->assertListed($this->tenant, [$this->ticket, $this->formerTicket]);
        // autor svůj tiket vidí i po skončení smlouvy
        $this->assertListed($this->formerTenant, [$this->formerTicket]);
        // správce-nájemník: tikety spravované nemovitosti i svůj z vlastního bytu (#24)
        $this->assertListed($this->manager, [$this->ticket, $this->formerTicket, $this->managerTicket]);
        $this->assertListed($this->strangerManager, [$this->managerTicket]);
        $this->assertListed($this->otherLandlord, [$this->managerTicket]);

        foreach ([$this->landlord, $this->tenant, $this->manager] as $user) {
            $this->actingAs($user)->getJson($this->apiUrl("/tickets/{$this->ticket->id}"))->assertStatus(200);
        }
        foreach ($this->outsiders() as $user) {
            $this->actingAs($user)->getJson($this->apiUrl("/tickets/{$this->ticket->id}"))->assertStatus(403);
        }
        $this->actingAs($this->manager)->getJson($this->apiUrl("/tickets/{$this->managerTicket->id}"))->assertStatus(200);
        $this->actingAs($this->landlord)->getJson($this->apiUrl('/tickets/999999'))->assertStatus(404);
    }

    public function test_create_only_with_relation_to_property(): void
    {
        foreach ([$this->landlord, $this->manager, $this->tenant] as $user) {
            $this->storeTicket($user, $this->property)->assertStatus(201)->assertJsonPath('tenant_id', $user->id);
        }
        // správce-nájemník založí tiket ve svém bytě (#24)
        $this->storeTicket($this->manager, $this->otherProperty)->assertStatus(201);

        // #11: cizí nemovitost, bývalý nájemník ani nepřiřazený správce
        foreach ($this->outsiders() as $user) {
            $this->storeTicket($user, $this->property)->assertStatus(403);
        }
        $this->storeTicket($this->tenant, $this->otherProperty)->assertStatus(403);
        $this->assertSame(0, Ticket::query()->where('property_id', $this->property->id)
            ->whereIn('tenant_id', array_map(fn (User $user) => $user->id, $this->outsiders()))
            ->where('title', 'Kape kohoutek')->count());
    }

    public function test_author_edits_title_and_description_only_while_new(): void
    {
        $url = $this->apiUrl("/tickets/{$this->ticket->id}");

        $this->actingAs($this->tenant)->putJson($url, ['title' => 'Kape kohoutek v kuchyni'])->assertStatus(200);
        // stav, priorita ani řešitel autorovi nepatří
        $this->actingAs($this->tenant)->putJson($url, ['status' => TicketStatus::Resolved->value])->assertStatus(403);
        $this->actingAs($this->tenant)->putJson($url, ['title' => 'Jiný název', 'priority' => 'urgent'])->assertStatus(403);

        $this->ticket->update(['status' => TicketStatus::InProgress]);
        $this->actingAs($this->tenant)->putJson($url, ['title' => 'Pozdě'])->assertStatus(403);
        $this->assertSame('Kape kohoutek v kuchyni', $this->ticket->refresh()->title);

        // správce svůj tiket v cizím bytě upraví jen jako autor, stav ne
        $own = $this->apiUrl("/tickets/{$this->managerTicket->id}");
        $this->actingAs($this->manager)->putJson($own, ['description' => 'Teče voda pod dřezem.'])->assertStatus(200);
        $this->actingAs($this->manager)->putJson($own, ['status' => TicketStatus::Resolved->value])->assertStatus(403);
    }

    public function test_owner_and_assigned_manager_resolve_others_cannot(): void
    {
        $url = $this->apiUrl("/tickets/{$this->ticket->id}");

        foreach ([$this->landlord, $this->manager] as $user) {
            $this->actingAs($user)->putJson($url, ['status' => TicketStatus::InProgress->value])->assertStatus(200);
        }
        foreach ([...$this->outsiders(), $this->tenant] as $user) {
            $this->actingAs($user)->putJson($url, ['status' => TicketStatus::Resolved->value])->assertStatus(403);
            $this->actingAs($user)->deleteJson($url)->assertStatus(403);
        }
        $this->actingAs($this->manager)->deleteJson($url)->assertStatus(403);
        $this->assertSame(TicketStatus::InProgress, $this->ticket->refresh()->status);
    }

    public function test_assignee_must_be_manager_of_ticket_property(): void
    {
        $url = $this->apiUrl("/tickets/{$this->ticket->id}");

        $this->actingAs($this->landlord)->putJson($url, ['assigned_to' => $this->manager->id])->assertStatus(200);
        // správce si tiket vezme nebo ho vrátí (rozhodnutí při plánu)
        $this->actingAs($this->manager)->putJson($url, ['assigned_to' => null])->assertStatus(200);
        $this->actingAs($this->manager)->putJson($url, ['assigned_to' => $this->manager->id])->assertStatus(200);

        // #23: nepřiřazený správce, nájemník ani neexistující účet řešitelem být nemůže
        foreach ([$this->strangerManager->id, $this->tenant->id, 999999] as $assignee) {
            $this->actingAs($this->landlord)->putJson($url, ['assigned_to' => $assignee])
                ->assertStatus(422)
                ->assertJsonValidationErrors('assigned_to');
        }
        // cizí dostane 403 dřív, než by se kontroloval řešitel
        $this->actingAs($this->otherLandlord)->putJson($url, ['assigned_to' => $this->strangerManager->id])->assertStatus(403);
        $this->assertSame($this->manager->id, $this->ticket->refresh()->assigned_to);
    }

    public function test_comments_and_images_only_for_ticket_viewers(): void
    {
        $comments = $this->apiUrl("/tickets/{$this->ticket->id}/comments");
        $images = $this->apiUrl("/tickets/{$this->ticket->id}/images");

        foreach ([$this->landlord, $this->manager, $this->tenant] as $user) {
            $this->actingAs($user)->getJson($comments)->assertStatus(200);
            $this->actingAs($user)->getJson($images)->assertStatus(200);
            $this->actingAs($user)->postJson($comments, ['message' => 'Mrknu na to.'])->assertStatus(201);
            $this->actingAs($user)->post($images, ['image' => UploadedFile::fake()->image('kohoutek.jpg')], ['Accept' => 'application/json'])
                ->assertStatus(201);
        }

        // #10: nájemník cizí nemovitosti (scénář Tomáš), cizí pronajímatel, nepřiřazený správce
        foreach ($this->outsiders() as $user) {
            $this->actingAs($user)->getJson($comments)->assertStatus(403);
            $this->actingAs($user)->getJson($images)->assertStatus(403);
            $this->actingAs($user)->postJson($comments, ['message' => 'Cizí komentář'])->assertStatus(403);
            $this->actingAs($user)->post($images, ['image' => UploadedFile::fake()->image('cizi.jpg')], ['Accept' => 'application/json'])
                ->assertStatus(403);
        }
        $this->assertSame(3, $this->ticket->comments()->count());
        $this->assertSame(3, $this->ticket->images()->count());
        $this->actingAs($this->landlord)->getJson($this->apiUrl('/tickets/999999/comments'))->assertStatus(404);
    }

    public function test_image_deleted_by_uploader_or_property_owner_only(): void
    {
        $byTenant = $this->image($this->tenant);
        $byManager = $this->image($this->manager);

        foreach ([$this->otherLandlord, $this->manager, $this->strangerManager] as $user) {
            $this->actingAs($user)->deleteJson($this->imageUrl($byTenant))->assertStatus(403);
        }
        $this->actingAs($this->tenant)->deleteJson($this->imageUrl($byTenant))->assertStatus(200);
        $this->actingAs($this->landlord)->deleteJson($this->imageUrl($byManager))->assertStatus(200);
        $this->assertSame(0, $this->ticket->images()->count());
    }

    public function test_image_shape_has_only_uploader_id_and_name(): void
    {
        $this->image($this->tenant);

        $list = $this->actingAs($this->manager)->getJson($this->apiUrl("/tickets/{$this->ticket->id}/images"))->assertStatus(200);
        $detail = $this->actingAs($this->manager)->getJson($this->apiUrl("/tickets/{$this->ticket->id}"))->assertStatus(200);

        $this->assertSame(['id', 'image_url', 'description', 'created_at', 'uploader'], array_keys($list->json('0')));
        $this->assertSame(['id', 'name'], array_keys($list->json('0.uploader')));
        $this->assertSame($list->json('0'), $detail->json('images.0'));
    }

    public function test_ticket_list_query_count_does_not_depend_on_ticket_count(): void
    {
        $few = $this->countListQueries($this->manager);
        Ticket::factory()->count(5)->create(['property_id' => $this->property->id]);

        $this->assertSame($few, $this->countListQueries($this->manager));
    }

    /**
     * @param  list<Ticket>  $tickets
     */
    private function assertListed(User $user, array $tickets): void
    {
        $ids = $this->actingAs($user)->getJson($this->apiUrl('/tickets'))->assertStatus(200)->json('data.*.id');
        sort($ids);
        $expected = array_map(fn (Ticket $ticket) => $ticket->id, $tickets);
        sort($expected);

        $this->assertSame($expected, $ids, "tikety uživatele {$user->email}");
    }

    private function storeTicket(User $user, Property $property): TestResponse
    {
        return $this->actingAs($user)->postJson($this->apiUrl('/tickets'), [
            'property_id' => $property->id,
            'title' => 'Kape kohoutek',
            'description' => 'V kuchyni kape kohoutek už třetí den.',
        ]);
    }

    private function image(User $uploader): TicketImage
    {
        return TicketImage::query()->create([
            'ticket_id' => $this->ticket->id,
            'uploaded_by' => $uploader->id,
            'image_path' => UploadedFile::fake()->image('foto.jpg')->store('tickets/'.$this->ticket->id.'/images', PrivateFile::DISK),
        ]);
    }

    private function imageUrl(TicketImage $image): string
    {
        return $this->apiUrl("/tickets/{$this->ticket->id}/images/{$image->id}");
    }

    private function countListQueries(User $user): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->getJson($this->apiUrl('/tickets'))->assertStatus(200);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
