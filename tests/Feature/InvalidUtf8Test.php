<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Property;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class InvalidUtf8Test extends TestCase
{
    use RefreshDatabase;

    private const string INVALID = "Kohout\xC3\x28ek"; // přerušená dvoubajtová sekvence

    private User $landlord;

    private User $tenant;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->landlord = User::factory()->landlord()->create();
        $this->tenant = User::factory()->tenant()->create();
        $property = Property::factory()->occupied()->create(['landlord_id' => $this->landlord->id]);
        Lease::factory()->create(['property_id' => $property->id, 'tenant_id' => $this->tenant->id]);
        $this->ticket = Ticket::factory()->create(['property_id' => $property->id, 'tenant_id' => $this->tenant->id]);
    }

    public function test_invalid_utf8_in_form_body_is_rejected_and_ticket_detail_keeps_working(): void
    {
        $url = $this->apiUrl("/tickets/{$this->ticket->id}/comments");

        // multipart / form-urlencoded tělo jde do Requestu bez dekódování (JSON s neplatným UTF-8 PHP nedekóduje)
        $this->actingAs($this->tenant)
            ->post($url, ['message' => self::INVALID], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'validation_error')
            ->assertJsonValidationErrors(['message' => 'UTF-8']);
        $this->assertDatabaseCount('ticket_comments', 0);

        $this->actingAs($this->tenant)
            ->post($url, ['message' => 'Kapající kohoutek v kuchyni'], ['Accept' => 'application/json'])
            ->assertStatus(201);

        $this->actingAs($this->landlord)
            ->getJson($this->apiUrl("/tickets/{$this->ticket->id}"))
            ->assertStatus(200);
    }

    public function test_invalid_utf8_in_nested_field_and_query_string_is_rejected(): void
    {
        $this->actingAs($this->landlord)
            ->put($this->apiUrl("/tickets/{$this->ticket->id}"), ['meta' => ['note' => self::INVALID]], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('meta.note');

        $this->actingAs($this->landlord)
            ->getJson($this->apiUrl('/tickets?search=Kohout%C3%28ek'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('search');

        $this->actingAs($this->landlord)
            ->getJson($this->apiUrl('/tickets?search=kohoutek'))
            ->assertStatus(200);
    }

    public function test_csv_import_in_windows_1250_returns_readable_error(): void
    {
        // „Datum;Částka;Variabilní symbol;Zpráva / … Nájem březen“ v cp1250 (mbstring cp1250 neumí)
        $csv = "Datum;\xC8\xE1stka;Variabiln\xED symbol;Zpr\xE1va\n15.03.2026;15000,00;99999;N\xE1jem b\xF8ezen";
        $this->assertFalse(mb_check_encoding($csv, 'UTF-8'));

        $this->actingAs($this->landlord)
            ->postJson($this->apiUrl('/payments/import-csv'), [
                'file' => UploadedFile::fake()->createWithContent('vypis.csv', $csv),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file' => 'UTF-8']);
    }
}
