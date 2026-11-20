<?php

namespace Tests\Feature;

use App\Enums\LeaseStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\PropertyRoles;
use Tests\TestCase;

/**
 * Platby (2.9): vidí vlastník, nájemník smlouvy a přiřazený správce u aktivních smluv. Zaplacení potvrzuje vlastník
 * a přiřazený správce (ne svou platbu), zakládá jen vlastník na svou smlouvu (#12), zaplacená platba se nesmaže
 * 422 (#41), days_overdue je celé číslo.
 */
class PaymentAccessTest extends TestCase
{
    use PropertyRoles, RefreshDatabase;

    private Payment $payment;

    private Payment $managerPayment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createPropertyRoles();
        $this->payment = Payment::factory()->create(['lease_id' => $this->lease->id]);
        $this->managerPayment = Payment::factory()->create(['lease_id' => $this->managerLease->id]);
        Payment::factory()->create(['lease_id' => $this->endedLease->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_list_and_detail_by_relation_to_lease(): void
    {
        $this->assertSame(2, count($this->listIds($this->landlord)));
        $this->assertSame([$this->payment->id], $this->listIds($this->tenant));
        // správce, který je jinde nájemníkem: platby aktivních smluv spravované nemovitosti + svou vlastní,
        // ne platby ukončené smlouvy
        $this->assertEqualsCanonicalizing([$this->payment->id, $this->managerPayment->id], $this->listIds($this->manager));
        $this->assertSame([$this->managerPayment->id], $this->listIds($this->strangerManager));

        foreach ([$this->landlord, $this->tenant, $this->manager] as $user) {
            $this->actingAs($user)->getJson($this->apiUrl("/payments/{$this->payment->id}"))->assertStatus(200);
        }
        // nájemné a VS smlouvy vidí správce i ve vnořené smlouvě (páruje platby), kauci ne
        $this->actingAs($this->manager)
            ->getJson($this->apiUrl("/payments/{$this->payment->id}"))
            ->assertJsonPath('lease.rent_amount', $this->lease->rent_amount)
            ->assertJsonPath('lease.variable_symbol', $this->lease->variable_symbol)
            ->assertJsonMissingPath('lease.deposit_amount');
        foreach ($this->outsiders() as $user) {
            $this->actingAs($user)->getJson($this->apiUrl("/payments/{$this->payment->id}"))->assertStatus(403);
        }
        $this->actingAs($this->landlord)->getJson($this->apiUrl('/payments/99999'))->assertStatus(404);
    }

    public function test_only_owner_creates_payment_on_own_lease(): void
    {
        $payload = [
            'lease_id' => $this->lease->id,
            'type' => PaymentType::Other->value,
            'amount' => 500,
            'due_date' => now()->toDateString(),
        ];

        foreach ([$this->otherLandlord, $this->manager, $this->tenant] as $user) {
            $this->actingAs($user)->postJson($this->apiUrl('/payments'), $payload)->assertStatus(403);
        }
        $this->assertSame(1, Payment::query()->where('lease_id', $this->lease->id)->count());

        $this->actingAs($this->landlord)->postJson($this->apiUrl('/payments'), $payload)->assertStatus(201);
    }

    public function test_only_owner_updates_and_deletes_and_paid_payment_is_422(): void
    {
        foreach ([$this->otherLandlord, $this->manager, $this->tenant] as $user) {
            $this->actingAs($user)->putJson($this->apiUrl("/payments/{$this->payment->id}"), ['amount' => 1])->assertStatus(403);
            $this->actingAs($user)->deleteJson($this->apiUrl("/payments/{$this->payment->id}"))->assertStatus(403);
        }
        foreach ([$this->otherLandlord, $this->tenant, $this->strangerManager, $this->formerTenant] as $user) {
            $this->actingAs($user)->putJson($this->apiUrl("/payments/{$this->payment->id}/mark-paid"))->assertStatus(403);
        }
        $this->assertSame(PaymentStatus::Unpaid, $this->payment->refresh()->status);

        $paid = Payment::factory()->paid()->create(['lease_id' => $this->lease->id]);
        $this->actingAs($this->landlord)->deleteJson($this->apiUrl("/payments/{$paid->id}"))->assertStatus(422);
        $this->actingAs($this->otherLandlord)->deleteJson($this->apiUrl("/payments/{$paid->id}"))->assertStatus(403);
        $this->assertNotSoftDeleted($paid);

        $this->actingAs($this->landlord)->deleteJson($this->apiUrl("/payments/{$this->payment->id}"))->assertStatus(200);
    }

    public function test_assigned_manager_marks_payment_paid_and_recalculates_trust_score(): void
    {
        // zaplaceno po splatnosti → skóre nájemníka se změní (listener RecalculateTrustScore)
        $this->payment->update(['due_date' => now()->subDays(3)]);
        $oldScore = $this->tenant->trust_score;

        $this->actingAs($this->manager)
            ->putJson($this->apiUrl("/payments/{$this->payment->id}/mark-paid"))
            ->assertStatus(200)
            ->assertJsonPath('status', PaymentStatus::Paid->value);

        $this->assertSame(PaymentStatus::Paid, $this->payment->refresh()->status);
        $this->assertNotEquals($oldScore, $this->tenant->refresh()->trust_score);
    }

    public function test_owner_marks_payment_of_ended_or_terminated_lease_paid(): void
    {
        // poslední nájem a nedoplatky chodí po konci smlouvy; přiřazený správce tu už potvrdit nesmí
        $terminated = Lease::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->formerTenant->id,
            'status' => LeaseStatus::Terminated,
        ]);

        foreach ([$this->endedLease, $terminated] as $lease) {
            $payment = Payment::factory()->create(['lease_id' => $lease->id]);

            $this->actingAs($this->manager)
                ->putJson($this->apiUrl("/payments/{$payment->id}/mark-paid"))
                ->assertStatus(403);
            $this->actingAs($this->landlord)
                ->putJson($this->apiUrl("/payments/{$payment->id}/mark-paid"))
                ->assertStatus(200)
                ->assertJsonPath('status', PaymentStatus::Paid->value);
            $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        }
    }

    public function test_manager_cannot_mark_own_payment_or_payment_of_ended_lease(): void
    {
        // svou platbu jinde (tam není správce) ani tam, kde je zároveň správcem i nájemníkem
        $ownHere = Payment::factory()->create(['lease_id' => Lease::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->manager->id,
        ])->id]);
        $ended = Payment::factory()->create(['lease_id' => $this->endedLease->id]);

        foreach ([$this->managerPayment, $ownHere, $ended] as $payment) {
            $this->actingAs($this->manager)
                ->putJson($this->apiUrl("/payments/{$payment->id}/mark-paid"))
                ->assertStatus(403);
            $this->assertSame(PaymentStatus::Unpaid, $payment->refresh()->status);
        }

        // jeho platbu potvrdí správce té nemovitosti
        $this->actingAs($this->strangerManager)
            ->putJson($this->apiUrl("/payments/{$this->managerPayment->id}/mark-paid"))
            ->assertStatus(200);
    }

    public function test_generate_and_import_only_for_landlord(): void
    {
        $file = UploadedFile::fake()->createWithContent('vypis.csv', "Datum;Částka;VS\n15.03.2026;100;1");

        foreach ([$this->manager, $this->tenant] as $user) {
            $this->actingAs($user)->postJson($this->apiUrl('/payments/generate-monthly'))->assertStatus(403);
            $this->actingAs($user)->postJson($this->apiUrl('/payments/import-csv'), ['file' => $file])->assertStatus(403);
        }
    }

    public function test_days_overdue_is_whole_number_and_null_when_due_today(): void
    {
        Carbon::setTestNow('2026-03-20 17:45');
        $late = Payment::factory()->create(['lease_id' => $this->lease->id, 'due_date' => '2026-03-10']);
        $today = Payment::factory()->create(['lease_id' => $this->lease->id, 'due_date' => '2026-03-20']);
        $paid = Payment::factory()->paid()->create(['lease_id' => $this->lease->id, 'due_date' => '2026-03-01']);

        $this->assertSame(10, $this->daysOverdue($late));
        $this->assertNull($this->daysOverdue($today));
        $this->assertNull($this->daysOverdue($paid));
    }

    public function test_payment_list_query_count_does_not_depend_on_payment_count(): void
    {
        $few = $this->countListQueries();
        Payment::factory()->count(5)->create(['lease_id' => $this->lease->id]);

        $this->assertSame($few, $this->countListQueries());
    }

    /**
     * @return list<int>
     */
    private function listIds(User $user): array
    {
        return $this->actingAs($user)->getJson($this->apiUrl('/payments'))->assertStatus(200)->json('data.*.id');
    }

    private function daysOverdue(Payment $payment): mixed
    {
        return $this->actingAs($this->landlord)
            ->getJson($this->apiUrl("/payments/{$payment->id}"))
            ->assertStatus(200)
            ->json('days_overdue');
    }

    private function countListQueries(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->landlord)->getJson($this->apiUrl('/payments'))->assertStatus(200);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
