<?php

namespace Tests\Feature\Actions;

use App\Actions\Payments\ImportBankPayments;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Rating;
use App\Notifications\PaymentOverdueNotification;
use App\Services\TrustScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use RuntimeException;
use Tests\Feature\Concerns\PropertyRoles;
use Tests\TestCase;

/**
 * Platby (#32, #34, #36, #37): generování jen pro platné smlouvy, robustní kontrola po splatnosti,
 * přepočty trust score a CSV import.
 */
class PaymentRulesTest extends TestCase
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

    public function test_generation_skips_leases_not_valid_in_the_month_and_deleted_properties(): void
    {
        Carbon::setTestNow('2026-03-01 01:00');
        $own = fn (array $attributes) => Lease::factory()->create([
            'property_id' => Property::factory()->create(['landlord_id' => $this->landlord->id])->id,
            'utility_advances' => 0,
            ...$attributes,
        ]);
        $endedInFebruary = $own(['start_date' => '2025-03-01', 'end_date' => '2026-02-28']);
        $startsInApril = $own(['start_date' => '2026-04-01', 'end_date' => null]);
        $startsInMarch = $own(['start_date' => '2026-03-20', 'end_date' => null]);
        $onDeletedProperty = $own(['start_date' => '2025-01-01', 'end_date' => null]);
        $onDeletedProperty->property?->delete();

        $this->artisan('rentflow:generate-payments')->assertSuccessful();

        $this->assertSame(0, $endedInFebruary->payments()->count());
        $this->assertSame(0, $startsInApril->payments()->count());
        $this->assertSame(1, $startsInMarch->payments()->count());
        $this->assertSame(0, $onDeletedProperty->payments()->count());
    }

    public function test_overdue_check_skips_orphans_and_recalculates_each_tenant_once(): void
    {
        Carbon::setTestNow('2026-03-20 08:00');
        $late = ['status' => PaymentStatus::Unpaid, 'due_date' => '2026-03-15'];
        $first = Payment::factory()->create(['lease_id' => $this->lease->id, ...$late]);
        $second = Payment::factory()->create(['lease_id' => $this->lease->id, ...$late, 'type' => PaymentType::Utilities]);
        $deletedLease = Lease::factory()->create();
        $onDeletedLease = Payment::factory()->create(['lease_id' => $deletedLease->id, ...$late]);
        $deletedLease->delete();
        $deletedProperty = Property::factory()->create();
        $onDeletedProperty = Payment::factory()->create([
            'lease_id' => Lease::factory()->create(['property_id' => $deletedProperty->id])->id, ...$late,
        ]);
        $deletedProperty->delete();

        $this->partialMock(TrustScoreService::class, function (MockInterface $mock) {
            $mock->shouldReceive('calculate')->once()->passthru();
        });

        $this->artisan('rentflow:check-overdue-payments')->assertSuccessful();

        $this->assertSame(PaymentStatus::Overdue, $first->refresh()->status);
        $this->assertSame(PaymentStatus::Overdue, $second->refresh()->status);
        $this->assertSame(PaymentStatus::Unpaid, $onDeletedLease->refresh()->status);
        $this->assertSame(PaymentStatus::Unpaid, $onDeletedProperty->refresh()->status);
        Notification::assertSentToTimes($this->tenant, PaymentOverdueNotification::class, 2);
    }

    public function test_rating_and_its_deletion_recalculate_trust_score(): void
    {
        $this->endedLease->ratings()->delete();
        $before = (float) $this->formerTenant->refresh()->trust_score;

        $this->actingAs($this->landlord)
            ->postJson($this->apiUrl("/leases/{$this->endedLease->id}/ratings"), ['category' => 'overall', 'score' => 1])
            ->assertStatus(201);
        $rated = (float) $this->formerTenant->refresh()->trust_score;
        $this->assertNotEquals($before, $rated);

        $rating = Rating::query()->where('lease_id', $this->endedLease->id)->firstOrFail();
        $this->actingAs($this->landlord)->deleteJson($this->apiUrl("/ratings/{$rating->id}"))->assertStatus(200);
        $this->assertNotEquals($rated, (float) $this->formerTenant->refresh()->trust_score);
    }

    public function test_put_status_paid_acts_like_mark_paid_and_paid_payment_cannot_be_marked_again(): void
    {
        $payment = Payment::factory()->create([
            'lease_id' => $this->lease->id, 'status' => PaymentStatus::Unpaid, 'due_date' => today()->subDays(20),
        ]);
        $this->partialMock(TrustScoreService::class, function (MockInterface $mock) {
            $mock->shouldReceive('calculate')->once()->passthru();
        });

        $this->actingAs($this->landlord)
            ->putJson($this->apiUrl("/payments/{$payment->id}"), ['status' => PaymentStatus::Paid->value])
            ->assertStatus(200);
        $this->assertSame(today()->toDateString(), $payment->refresh()->paid_date?->toDateString());

        $this->actingAs($this->landlord)
            ->putJson($this->apiUrl("/payments/{$payment->id}/mark-paid"))
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_csv_pairs_advances_by_payment_symbol_and_leaves_amount_mismatch_for_review(): void
    {
        $this->lease->update(['variable_symbol' => '5550']);
        $rent = Payment::factory()->create([
            'lease_id' => $this->lease->id, 'type' => PaymentType::Rent, 'amount' => 15000, 'variable_symbol' => '5550',
        ]);
        $advance = Payment::factory()->create([
            'lease_id' => $this->lease->id, 'type' => PaymentType::Utilities, 'amount' => 3000, 'variable_symbol' => '55501',
        ]);

        $csv = "Datum;Částka;Variabilní symbol\n05.03.2026;3 000,00;55501\n05.03.2026;14 000,00;5550\n05.03.2026;-500,00;5550";
        $this->actingAs($this->landlord)
            ->postJson($this->apiUrl('/payments/import-csv'), ['file' => UploadedFile::fake()->createWithContent('vypis.csv', $csv)])
            ->assertStatus(200)
            ->assertJsonPath('summary.matched', 1)
            ->assertJsonPath('summary.to_review', 1)
            ->assertJsonPath('summary.unmatched', 1)
            ->assertJsonPath('details.to_review.0.payment_id', $rent->id);

        $this->assertSame(PaymentStatus::Paid, $advance->refresh()->status);
        $this->assertSame('2026-03-05', $advance->paid_date?->toDateString());
        $this->assertSame(PaymentStatus::Unpaid, $rent->refresh()->status);
    }

    public function test_failed_csv_import_saves_nothing(): void
    {
        $this->lease->update(['variable_symbol' => '5550']);
        $payment = Payment::factory()->create(['lease_id' => $this->lease->id, 'amount' => 15000, 'variable_symbol' => '5550']);
        $this->partialMock(TrustScoreService::class, function (MockInterface $mock) {
            $mock->shouldReceive('calculate')->andThrow(new RuntimeException('výpadek'));
        });

        try {
            app(ImportBankPayments::class)->handle($this->landlord, "Datum;Částka;VS\n05.03.2026;15000;5550");
            $this->fail('Import měl selhat.');
        } catch (RuntimeException $e) {
            $this->assertSame('výpadek', $e->getMessage());
        }

        $this->assertSame(PaymentStatus::Unpaid, $payment->refresh()->status);
    }
}
