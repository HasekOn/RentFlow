<?php

namespace Tests\Feature;

use App\Actions\Payments\ImportBankPayments;
use App\Enums\DocumentVisibility;
use App\Enums\LeaseStatus;
use App\Enums\PaymentType;
use App\Enums\PropertyStatus;
use App\Enums\TicketStatus;
use App\Models\Document;
use App\Models\Lease;
use App\Models\Notice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Rating;
use App\Models\Ticket;
use App\Models\TicketImage;
use App\Models\User;
use App\Notifications\TenantInvitationNotification;
use App\Support\PrivateFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Concerns\PropertyRoles;
use Tests\TestCase;

/**
 * Opravy z bezpečnostních auditů: S3–S11, B-1 až B-3, tikety po konci a od začátku smlouvy, zákaz úpravy
 * pozvánky (S1 + S2 jsou v LeaseAcceptanceTest).
 */
class SecurityFixesTest extends TestCase
{
    use PropertyRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake(PrivateFile::DISK);
        $this->createPropertyRoles();
    }

    public function test_s3_rating_details_only_for_own_ratings(): void
    {
        Rating::factory()->create([
            'lease_id' => $this->lease->id, 'rated_by' => $this->landlord->id, 'comment' => 'Hlučný soused',
        ]);
        // dřívější přijatá smlouva u cizího pronajímatele, ten nájemníka smí vidět
        $earlier = Lease::factory()->ended()->create(['property_id' => $this->otherProperty->id, 'tenant_id' => $this->tenant->id]);
        Rating::factory()->create(['lease_id' => $earlier->id, 'rated_by' => $this->otherLandlord->id, 'comment' => 'Vzorný']);

        $ratings = collect($this->actingAs($this->otherLandlord)
            ->getJson($this->apiUrl("/users/{$this->tenant->id}/ratings"))
            ->assertStatus(200)
            ->assertJsonCount(2)
            ->json());

        $own = $ratings->firstWhere('comment', 'Vzorný');
        $this->assertNotNull($own);
        $this->assertSame($this->otherLandlord->id, $own['rated_by']['id']);
        $foreign = $ratings->first(fn (array $rating) => ($rating['comment'] ?? null) !== 'Vzorný');
        $this->assertSame(['id', 'category', 'score', 'created_at'], array_keys($foreign));

        // hodnocený nájemník a vlastník nemovitosti vidí detail
        foreach ([$this->tenant, $this->landlord] as $user) {
            $comments = collect($this->actingAs($user)->getJson($this->apiUrl("/users/{$this->tenant->id}/ratings"))->json())
                ->pluck('comment');
            $this->assertContains('Hlučný soused', $comments);
        }
    }

    public function test_s4_markdown_in_user_text_is_not_rendered_as_link(): void
    {
        $this->property->update(['address' => '[Přihlaste se](https://evil.test/login) 12']);

        $html = (string) (new TenantInvitationNotification($this->lease->load('property')))->toMail($this->tenant)->render();

        $this->assertStringNotContainsString('href="https://evil.test/login"', $html);
        $this->assertStringContainsString('evil.test/login', $html);
    }

    public function test_s5_manager_does_not_see_or_confirm_deposits(): void
    {
        $rent = Payment::factory()->unpaid()->create(['lease_id' => $this->lease->id, 'type' => PaymentType::Rent]);
        $deposit = Payment::factory()->unpaid()->create(['lease_id' => $this->lease->id, 'type' => PaymentType::Deposit]);
        $ownDeposit = Payment::factory()->unpaid()->create(['lease_id' => $this->managerLease->id, 'type' => PaymentType::Deposit]);

        $ids = $this->actingAs($this->manager)->getJson($this->apiUrl('/payments'))->assertStatus(200)->json('data.*.id');
        $this->assertContains($rent->id, $ids);
        $this->assertContains($ownDeposit->id, $ids);
        $this->assertNotContains($deposit->id, $ids);

        $this->actingAs($this->manager)->getJson($this->apiUrl("/payments/{$deposit->id}"))->assertStatus(403);
        $this->actingAs($this->manager)->putJson($this->apiUrl("/payments/{$deposit->id}/mark-paid"))->assertStatus(403);
        $this->actingAs($this->manager)->putJson($this->apiUrl("/payments/{$rent->id}/mark-paid"))->assertStatus(200);
        // vlastník a nájemník kauci vidí dál
        $this->actingAs($this->landlord)->getJson($this->apiUrl("/payments/{$deposit->id}"))->assertStatus(200);
        $this->actingAs($this->tenant)->getJson($this->apiUrl("/payments/{$deposit->id}"))->assertStatus(200);
    }

    public function test_s6_lease_document_is_hidden_from_other_tenants(): void
    {
        $formerDocument = $this->upload(['lease_id' => $this->endedLease->id, 'name' => 'Předávací protokol 2024'])
            ->assertStatus(201)
            ->assertJsonPath('lease_id', $this->endedLease->id)
            ->json('id');
        $ownDocument = $this->upload(['lease_id' => $this->lease->id, 'name' => 'Předávací protokol 2025'])->json('id');
        $shared = $this->upload(['name' => 'Domovní řád'])->json('id');

        $ids = $this->actingAs($this->tenant)
            ->getJson($this->apiUrl("/properties/{$this->property->id}/documents"))
            ->assertStatus(200)
            ->json('*.id');
        $this->assertSame([$ownDocument, $shared], collect($ids)->sort()->values()->all());
        $this->actingAs($this->tenant)->get($this->apiUrl("/documents/{$formerDocument}/download"))->assertStatus(403);

        $this->upload(['lease_id' => $this->managerLease->id])->assertStatus(422)->assertJsonValidationErrors('lease_id');
        $this->assertSame(3, Document::query()->count());
    }

    public function test_s7_oversized_input_is_rejected(): void
    {
        $tooLong = Str::repeat('a', 5001);
        $cases = [
            ['post', '/leases', ['rent_amount' => '100000000'], 'rent_amount'],
            ['post', '/payments', ['amount' => '100000000'], 'amount'],
            ['post', '/payments', ['note' => $tooLong], 'note'],
            ['post', '/tickets', ['description' => $tooLong], 'description'],
            ['post', "/tickets/{$this->ticket()->id}/comments", ['message' => Str::repeat('a', 2001)], 'message'],
            ['post', '/properties', ['purchase_price' => '10000000000', 'description' => $tooLong], 'purchase_price'],
            ['post', "/properties/{$this->property->id}/notices", ['content' => $tooLong], 'content'],
            ['put', '/profile/password', ['current_password' => Str::repeat('a', 256)], 'current_password'],
        ];

        foreach ($cases as [$method, $url, $payload, $field]) {
            $this->actingAs($this->landlord)
                ->json($method, $this->apiUrl($url), $payload)
                ->assertStatus(422)
                ->assertJsonValidationErrors($field);
        }

        $this->postJson($this->apiUrl('/login'), ['email' => Str::repeat('a', 250).'@x.cz', 'password' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_s8_paid_payment_only_note_can_change(): void
    {
        $payment = Payment::factory()->paid()->create(['lease_id' => $this->lease->id, 'amount' => 12000]);

        foreach ([['amount' => 1], ['status' => 'unpaid'], ['due_date' => '2020-01-01']] as $change) {
            $this->actingAs($this->landlord)
                ->putJson($this->apiUrl("/payments/{$payment->id}"), $change)
                ->assertStatus(422)
                ->assertJsonValidationErrors('status');
        }

        $this->actingAs($this->landlord)
            ->putJson($this->apiUrl("/payments/{$payment->id}"), ['note' => 'Zaplaceno hotově', 'amount' => 12000])
            ->assertStatus(200)
            ->assertJsonPath('note', 'Zaplaceno hotově');
        $this->assertSame('12000.00', $payment->refresh()->amount);
    }

    public function test_s9_email_change_requires_current_password(): void
    {
        $email = $this->tenant->email;

        $this->actingAs($this->tenant)->putJson($this->apiUrl('/profile'), ['email' => 'novy@example.test'])
            ->assertStatus(422)->assertJsonValidationErrors('current_password');
        $this->actingAs($this->tenant)->putJson($this->apiUrl('/profile'), ['email' => 'novy@example.test', 'current_password' => 'spatne'])
            ->assertStatus(422)->assertJsonValidationErrors('current_password');
        $this->assertSame($email, $this->tenant->refresh()->email);

        // jméno a telefon bez hesla, stejný e-mail taky
        $this->actingAs($this->tenant)->putJson($this->apiUrl('/profile'), ['name' => 'Nové Jméno', 'email' => $email])
            ->assertStatus(200);
        $this->actingAs($this->tenant)->putJson($this->apiUrl('/profile'), ['email' => 'novy@example.test', 'current_password' => 'password'])
            ->assertStatus(200);
        $this->assertSame('novy@example.test', $this->tenant->refresh()->email);
    }

    public function test_s10_variable_symbol_is_unique_per_landlord(): void
    {
        $this->managerLease->update(['variable_symbol' => '55555']);
        $free = Property::factory()->available()->create(['landlord_id' => $this->landlord->id]);

        $id = $this->actingAs($this->landlord)->postJson($this->apiUrl('/leases'), $this->leasePayload($free, '55555'))
            ->assertStatus(201)
            ->json('id');
        $this->actingAs($this->landlord)->postJson($this->apiUrl('/leases'), $this->leasePayload($this->property, '55555'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('variable_symbol');

        $this->actingAs($this->landlord)->putJson($this->apiUrl("/leases/{$id}"), ['variable_symbol' => '55555'])->assertStatus(200);
        $this->actingAs($this->landlord)->putJson($this->apiUrl("/leases/{$this->lease->id}"), ['variable_symbol' => '55555'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('variable_symbol');
    }

    public function test_s11_csv_import_has_row_limit_and_own_throttle(): void
    {
        $rows = str_repeat("15.03.2026;100,00;99\n", ImportBankPayments::MAX_ROWS + 1);
        $this->actingAs($this->landlord)
            ->postJson($this->apiUrl('/payments/import-csv'), ['file' => $this->csv("Datum;Částka;Variabilní symbol\n".$rows)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $small = "Datum;Částka;Variabilní symbol\n15.03.2026;100,00;99";
        for ($i = 2; $i <= 10; $i++) {
            $this->actingAs($this->landlord)->postJson($this->apiUrl('/payments/import-csv'), ['file' => $this->csv($small)])->assertStatus(200);
        }
        $this->actingAs($this->landlord)->postJson($this->apiUrl('/payments/import-csv'), ['file' => $this->csv($small)])->assertStatus(429);
    }

    public function test_author_without_lease_comments_only_open_ticket(): void
    {
        $open = $this->ticket(['tenant_id' => $this->formerTenant->id, 'status' => TicketStatus::InProgress]);
        $resolved = $this->ticket(['tenant_id' => $this->formerTenant->id, 'status' => TicketStatus::Resolved]);

        $this->actingAs($this->formerTenant)->postJson($this->apiUrl("/tickets/{$open->id}/comments"), ['message' => 'Pořád kape'])
            ->assertStatus(201);
        $this->actingAs($this->formerTenant)->postJson($this->apiUrl("/tickets/{$resolved->id}/comments"), ['message' => 'Zase kape'])
            ->assertStatus(403);
        $this->actingAs($this->formerTenant)->getJson($this->apiUrl("/tickets/{$resolved->id}/comments"))->assertStatus(200);
        $this->assertTrue($this->formerTenant->can('create', [TicketImage::class, $open]));
        $this->assertFalse($this->formerTenant->can('create', [TicketImage::class, $resolved]));

        // kdo nemovitost vidí, komentuje i vyřešený tiket
        foreach ([$this->landlord, $this->manager, $this->tenant] as $user) {
            $this->actingAs($user)->postJson($this->apiUrl("/tickets/{$resolved->id}/comments"), ['message' => 'Ok'])->assertStatus(201);
        }
    }

    public function test_b1_landlord_gets_pdf_only_for_accepted_lease(): void
    {
        $free = Property::factory()->available()->create(['landlord_id' => $this->landlord->id]);
        $tenantOnly = ['property_id' => $free->id, 'tenant_id' => $this->formerTenant->id];
        $unaccepted = [
            Lease::factory()->pending()->create($tenantOnly),
            Lease::factory()->pending()->create([...$tenantOnly, 'status' => LeaseStatus::Terminated]),
            Lease::factory()->declined()->create($tenantOnly),
        ];

        foreach ($unaccepted as $lease) {
            $this->actingAs($this->landlord)->get($this->apiUrl("/leases/{$lease->id}/generate-pdf"))->assertStatus(403);
            // nájemník si návrh smlouvy přečte i před přijetím
            $this->actingAs($this->formerTenant)->get($this->apiUrl("/leases/{$lease->id}/generate-pdf"))->assertStatus(200);
        }
        $this->actingAs($this->landlord)->get($this->apiUrl("/leases/{$this->lease->id}/generate-pdf"))->assertStatus(200);
        $this->actingAs($this->manager)->get($this->apiUrl("/leases/{$this->lease->id}/generate-pdf"))->assertStatus(403);
    }

    public function test_b2_lease_detail_hides_deposits_from_manager(): void
    {
        Payment::factory()->unpaid()->create(['lease_id' => $this->lease->id, 'type' => PaymentType::Rent]);
        Payment::factory()->unpaid()->create(['lease_id' => $this->lease->id, 'type' => PaymentType::Deposit]);
        Payment::factory()->unpaid()->create(['lease_id' => $this->managerLease->id, 'type' => PaymentType::Deposit]);

        $types = fn (User $user, Lease $lease) => $this->actingAs($user)
            ->getJson($this->apiUrl("/leases/{$lease->id}"))
            ->assertStatus(200)
            ->json('payments.*.type');

        $this->assertSame(['rent'], $types($this->manager, $this->lease));
        foreach ([$this->landlord, $this->tenant] as $user) {
            $this->assertEqualsCanonicalizing(['rent', 'deposit'], $types($user, $this->lease));
        }
        // správce jako nájemník svou kauci vidí
        $this->assertSame(['deposit'], $types($this->manager, $this->managerLease));
    }

    public function test_b3_tenant_is_invited_by_email_without_revealing_accounts(): void
    {
        $free = Property::factory()->available()->create(['landlord_id' => $this->landlord->id]);
        $payload = fn (?string $email) => [
            'property_id' => $free->id, 'tenant_email' => $email, 'start_date' => now()->addMonth()->toDateString(), 'rent_amount' => 10000,
        ];

        $messages = [];
        foreach (['nikdo@example.test', $this->otherLandlord->email] as $email) {
            $messages[] = $this->actingAs($this->landlord)->postJson($this->apiUrl('/leases'), $payload($email))
                ->assertStatus(422)
                ->assertJsonValidationErrors('tenant_email')
                ->assertJsonMissingPath('tenant')
                ->json('errors.tenant_email.0');
        }
        $this->assertSame($messages[0], $messages[1]);
        // tenant_id z klienta se ignoruje, bez e-mailu smlouva nevznikne
        $this->actingAs($this->landlord)
            ->postJson($this->apiUrl('/leases'), [...$payload(null), 'tenant_id' => $this->formerTenant->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tenant_email');
        $this->assertSame(0, Lease::query()->where('property_id', $free->id)->count());
        Notification::assertNothingSent();

        // u nemovitosti v rekonstrukci stejná chyba pro existující i neexistující účet
        $free->update(['status' => PropertyStatus::Renovation]);
        foreach (['nikdo@example.test', $this->formerTenant->email] as $email) {
            $this->actingAs($this->landlord)->postJson($this->apiUrl('/leases'), $payload($email))
                ->assertStatus(422)
                ->assertJsonValidationErrors('property_id')
                ->assertJsonMissingValidationErrors('tenant_email');
        }
        $free->update(['status' => PropertyStatus::Available]);

        $this->actingAs($this->landlord)->postJson($this->apiUrl('/leases'), $payload($this->formerTenant->email))
            ->assertStatus(201)
            ->assertJsonPath('tenant.id', $this->formerTenant->id);
    }

    public function test_b3_invitations_share_throttle_with_account_lookup(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($this->landlord)->getJson($this->apiUrl("/users/lookup?email=nikdo{$i}@example.test"))->assertStatus(404);
        }

        $this->actingAs($this->landlord)->postJson($this->apiUrl('/leases'), ['tenant_email' => 'nikdo@example.test'])->assertStatus(429);
        // jiný pronajímatel má vlastní počítadlo
        $this->actingAs($this->otherLandlord)->getJson($this->apiUrl('/users/lookup?email=nikdo@example.test'))->assertStatus(404);
    }

    public function test_tenant_sees_only_tickets_since_own_lease_start(): void
    {
        $history = $this->ticket(['tenant_id' => $this->formerTenant->id, 'created_at' => $this->lease->start_date->copy()->subDay()]);
        $sinceStart = $this->ticket(['tenant_id' => $this->landlord->id, 'created_at' => $this->lease->start_date->copy()->addHour()]);
        $own = $this->ticket();

        $ids = fn (User $user) => $this->actingAs($user)->getJson($this->apiUrl('/tickets'))->assertStatus(200)->json('data.*.id');

        $this->assertEqualsCanonicalizing([$sinceStart->id, $own->id], $ids($this->tenant));
        $this->actingAs($this->tenant)->getJson($this->apiUrl("/tickets/{$history->id}"))->assertStatus(403);
        $this->actingAs($this->tenant)->getJson($this->apiUrl("/tickets/{$history->id}/comments"))->assertStatus(403);
        $this->actingAs($this->tenant)->postJson($this->apiUrl("/tickets/{$history->id}/comments"), ['message' => 'Ahoj'])->assertStatus(403);
        // historie zůstává vlastníkovi a správci, autor svůj tiket vidí dál
        foreach ([$this->landlord, $this->manager] as $user) {
            $this->assertEqualsCanonicalizing([$history->id, $sinceStart->id, $own->id], $ids($user));
        }
        $this->assertSame([$history->id], $ids($this->formerTenant));
    }

    public function test_pending_invitation_cannot_be_edited_only_withdrawn(): void
    {
        $free = Property::factory()->available()->create(['landlord_id' => $this->landlord->id]);
        $invitation = Lease::factory()->pending()->create(['property_id' => $free->id, 'tenant_id' => $this->formerTenant->id, 'rent_amount' => 10000]);

        foreach ([['rent_amount' => 9000], ['end_date' => now()->addYears(5)->toDateString()], ['status' => 'terminated', 'rent_amount' => 1]] as $change) {
            $this->actingAs($this->landlord)
                ->putJson($this->apiUrl("/leases/{$invitation->id}"), $change)
                ->assertStatus(422)
                ->assertJsonPath('errors.status.0', 'A pending invitation cannot be changed. Withdraw it and send a new one.');
        }
        $this->assertSame('10000.00', $invitation->refresh()->rent_amount);

        $this->actingAs($this->landlord)
            ->putJson($this->apiUrl("/leases/{$invitation->id}"), ['status' => 'terminated'])
            ->assertStatus(200)
            ->assertJsonPath('status', 'terminated');
    }

    public function test_future_tenant_sees_lease_and_payments_but_not_the_flat_before_start(): void
    {
        $future = User::factory()->tenant()->create();
        $start = $this->lease->end_date->copy()->addDay();
        $futureLease = Lease::factory()->create([
            'property_id' => $this->property->id, 'tenant_id' => $future->id, 'start_date' => $start, 'end_date' => null,
        ]);
        $payment = Payment::factory()->unpaid()->create(['lease_id' => $futureLease->id, 'type' => PaymentType::Deposit]);
        $notice = Notice::factory()->create(['property_id' => $this->property->id, 'created_by' => $this->landlord->id]);
        $document = $this->upload(['lease_id' => $futureLease->id, 'name' => 'Předávací protokol'])->json('id');
        $ticket = $this->ticket();

        // smlouva, PDF a platby hned po přijetí
        $this->assertContains($futureLease->id, $this->actingAs($future)->getJson($this->apiUrl('/leases'))->json('data.*.id'));
        $this->actingAs($future)->get($this->apiUrl("/leases/{$futureLease->id}/generate-pdf"))->assertStatus(200);
        $this->assertContains($payment->id, $this->actingAs($future)->getJson($this->apiUrl('/payments'))->json('data.*.id'));

        // byt, oznámení, dokumenty a tikety až od start_date
        $this->assertNotContains($this->property->id, $this->actingAs($future)->getJson($this->apiUrl('/properties'))->json('data.*.id'));
        foreach (['', '/notices', '/documents', '/meters', '/inventory'] as $path) {
            $this->actingAs($future)->getJson($this->apiUrl("/properties/{$this->property->id}{$path}"))->assertStatus(403);
        }
        $this->actingAs($future)->get($this->apiUrl("/documents/{$document}/download"))->assertStatus(403);
        $this->actingAs($future)->getJson($this->apiUrl("/tickets/{$ticket->id}"))->assertStatus(403);
        $this->actingAs($future)
            ->postJson($this->apiUrl('/tickets'), ['property_id' => $this->property->id, 'title' => 'Kape', 'description' => 'Kape kohoutek v koupelně'])
            ->assertStatus(403);
        // předchozí nájemník do konce své smlouvy normálně
        $this->actingAs($this->tenant)->getJson($this->apiUrl("/properties/{$this->property->id}/notices"))
            ->assertStatus(200)
            ->assertJsonPath('0.id', $notice->id);

        $this->travelTo($start->copy()->addHour());
        $this->actingAs($future)->getJson($this->apiUrl("/properties/{$this->property->id}"))->assertStatus(200);
        $this->actingAs($future)->getJson($this->apiUrl("/properties/{$this->property->id}/notices"))->assertStatus(200);
        $this->assertContains($document, $this->actingAs($future)
            ->getJson($this->apiUrl("/properties/{$this->property->id}/documents"))
            ->assertStatus(200)
            ->json('*.id'));
    }

    public function test_manager_sees_ratings_only_in_limited_shape_even_after_reactivation(): void
    {
        $property = Property::factory()->available()->create(['landlord_id' => $this->landlord->id]);
        $property->managers()->attach($this->manager->id);
        $lease = Lease::factory()->ended()->create(['property_id' => $property->id, 'tenant_id' => $this->formerTenant->id]);
        Rating::factory()->create(['lease_id' => $lease->id, 'rated_by' => $this->landlord->id, 'comment' => 'Pozdní platby']);

        $this->actingAs($this->manager)->getJson($this->apiUrl("/leases/{$lease->id}"))->assertStatus(403);
        $this->actingAs($this->landlord)
            ->putJson($this->apiUrl("/leases/{$lease->id}"), ['status' => 'active', 'end_date' => null])
            ->assertStatus(200);

        $limited = ['id', 'category', 'score', 'created_at'];
        $detail = $this->actingAs($this->manager)->getJson($this->apiUrl("/leases/{$lease->id}"))->assertStatus(200)->json('ratings');
        $this->assertSame($limited, array_keys($detail[0]));
        $list = $this->actingAs($this->manager)->getJson($this->apiUrl("/leases/{$lease->id}/ratings"))->assertStatus(200)->json();
        $this->assertSame($limited, array_keys($list[0]));

        // vlastník, autor a hodnocený nájemník vidí komentář
        foreach ([$this->landlord, $this->formerTenant] as $user) {
            $this->actingAs($user)->getJson($this->apiUrl("/leases/{$lease->id}/ratings"))
                ->assertStatus(200)
                ->assertJsonPath('0.comment', 'Pozdní platby');
            $this->actingAs($user)->getJson($this->apiUrl("/leases/{$lease->id}"))
                ->assertJsonPath('ratings.0.comment', 'Pozdní platby');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upload(array $data): TestResponse
    {
        return $this->actingAs($this->landlord)->postJson($this->apiUrl("/properties/{$this->property->id}/documents"), [
            'file' => UploadedFile::fake()->create('protokol.pdf', 10, 'application/pdf'),
            'document_type' => 'protocol',
            'name' => 'Dokument',
            'visibility' => DocumentVisibility::LandlordTenant->value,
            ...$data,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ticket(array $attributes = []): Ticket
    {
        return Ticket::factory()->create(['property_id' => $this->property->id, 'tenant_id' => $this->tenant->id, ...$attributes]);
    }

    /** @return array<string, mixed> */
    private function leasePayload(Property $property, string $variableSymbol): array
    {
        return [
            'property_id' => $property->id,
            'tenant_email' => $this->formerTenant->email,
            'start_date' => now()->addMonth()->toDateString(),
            'rent_amount' => 10000,
            'variable_symbol' => $variableSymbol,
        ];
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('vypis.csv', $content);
    }
}
