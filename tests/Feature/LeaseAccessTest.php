<?php

namespace Tests\Feature;

use App\Enums\LeaseStatus;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Concerns\PropertyRoles;
use Tests\TestCase;

/**
 * Smlouvy (2.8): kdo je vidí (Lease::visibleTo), výřez pro správce (nájemné, zálohy, VS a platby ano, kauce a PDF ne),
 * tenant_id, překryv a end_date (#22, #30).
 */
class LeaseAccessTest extends TestCase
{
    use PropertyRoles, RefreshDatabase;

    private const array FINANCE_FIELDS = ['rent_amount', 'deposit_amount', 'utility_advances', 'variable_symbol'];

    private const string RENT_SORT = '/order by\W+rent_amount\W/i';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createPropertyRoles();
        Payment::factory()->create(['lease_id' => $this->lease->id]);
    }

    public function test_list_shows_leases_by_relation_to_property(): void
    {
        $this->assertListed($this->landlord, [$this->lease, $this->endedLease]);
        $this->assertListed($this->tenant, [$this->lease]);
        $this->assertListed($this->formerTenant, [$this->endedLease]);
        $this->assertListed($this->otherLandlord, [$this->managerLease]);
        // přiřazený správce: aktivní smlouvy spravované nemovitosti + svou vlastní smlouvu nájemníka
        $this->assertListed($this->manager, [$this->lease, $this->managerLease]);
    }

    public function test_manager_sees_payment_fields_of_active_leases_but_own_lease_in_full(): void
    {
        // páruje platby ručně: nájemné, zálohy, VS a platby ano, kauce ne
        $managed = $this->actingAs($this->manager)->getJson($this->apiUrl("/leases/{$this->lease->id}"))->assertStatus(200);
        foreach (['rent_amount', 'utility_advances', 'variable_symbol'] as $field) {
            $this->assertArrayHasKey($field, $managed->json(), "{$field} chybí");
        }
        $managed->assertJsonCount(1, 'payments')->assertJsonMissingPath('deposit_amount');
        $this->actingAs($this->manager)->get($this->apiUrl("/leases/{$this->lease->id}/generate-pdf"))->assertStatus(403);
        $this->actingAs($this->manager)->getJson($this->apiUrl("/leases/{$this->endedLease->id}"))->assertStatus(403);

        $own = $this->actingAs($this->manager)->getJson($this->apiUrl("/leases/{$this->managerLease->id}"))->assertStatus(200);
        $this->assertHasFinance($own);

        $list = $this->actingAs($this->manager)->getJson($this->apiUrl('/leases'))->assertStatus(200);
        $rows = collect($list->json('data'))->keyBy('id');
        $this->assertArrayHasKey('rent_amount', $rows[$this->lease->id]);
        $this->assertArrayNotHasKey('deposit_amount', $rows[$this->lease->id]);
        $this->assertArrayHasKey('deposit_amount', $rows[$this->managerLease->id]);
    }

    public function test_owner_and_tenant_see_amounts_payments_and_pdf(): void
    {
        foreach ([$this->landlord, $this->tenant] as $user) {
            $response = $this->actingAs($user)->getJson($this->apiUrl("/leases/{$this->lease->id}"))->assertStatus(200);
            $this->assertHasFinance($response);
            $response->assertJsonCount(1, 'payments');
            $this->actingAs($user)->get($this->apiUrl("/leases/{$this->lease->id}/generate-pdf"))->assertStatus(200);
        }
    }

    public function test_outsiders_cannot_view_lease_or_pdf(): void
    {
        foreach ([...$this->outsiders(), $this->strangerManager] as $user) {
            $this->actingAs($user)->getJson($this->apiUrl("/leases/{$this->lease->id}"))->assertStatus(403);
            $this->actingAs($user)->get($this->apiUrl("/leases/{$this->lease->id}/generate-pdf"))->assertStatus(403);
        }
        $this->actingAs($this->landlord)->getJson($this->apiUrl('/leases/99999'))->assertStatus(404);
    }

    public function test_create_checks_owner_tenant_account_and_overlap(): void
    {
        $this->actingAs($this->otherLandlord)->postJson($this->apiUrl('/leases'), $this->payload($this->formerTenant))->assertStatus(403);
        $this->actingAs($this->manager)->postJson($this->apiUrl('/leases'), $this->payload($this->formerTenant))->assertStatus(403);

        // pronajímatel nemůže být nájemníkem smlouvy
        $this->actingAs($this->landlord)
            ->postJson($this->apiUrl('/leases'), $this->payload($this->otherLandlord))
            ->assertStatus(422)
            ->assertJsonValidationErrors('tenant_id');

        // období se překrývá s aktivní smlouvou nájemníka
        $this->actingAs($this->landlord)
            ->postJson($this->apiUrl('/leases'), $this->payload($this->formerTenant, now()->addMonth(), null))
            ->assertStatus(422)
            ->assertJsonPath('errors.start_date.0', 'Another active lease on this property overlaps the given period.');

        // navazující smlouva den po konci té současné projde, nájemníkem může být i správce
        $this->actingAs($this->landlord)
            ->postJson($this->apiUrl('/leases'), $this->payload($this->manager, $this->lease->end_date->copy()->addDay(), null))
            ->assertStatus(201);
    }

    public function test_update_checks_overlap_on_dates_and_reactivation(): void
    {
        $this->endedLease->update(['start_date' => now()->subYears(2), 'end_date' => now()->subMonth()]);
        $this->actingAs($this->landlord)
            ->putJson($this->apiUrl("/leases/{$this->endedLease->id}"), ['status' => LeaseStatus::Active->value])
            ->assertStatus(422);
        $this->assertSame(LeaseStatus::Ended, $this->endedLease->refresh()->status);

        $next = Lease::factory()->create([
            'property_id' => $this->property->id,
            'start_date' => $this->lease->end_date->copy()->addDay(),
            'end_date' => null,
        ]);
        $this->actingAs($this->landlord)
            ->putJson($this->apiUrl("/leases/{$this->lease->id}"), ['end_date' => $next->start_date->copy()->addMonth()->toDateString()])
            ->assertStatus(422);

        // změna bez období (nájemné) překryv nekontroluje
        $this->actingAs($this->landlord)
            ->putJson($this->apiUrl("/leases/{$this->lease->id}"), ['rent_amount' => 16000])
            ->assertStatus(200);
    }

    public function test_end_date_is_compared_with_stored_start_date(): void
    {
        $this->actingAs($this->landlord)
            ->putJson($this->apiUrl("/leases/{$this->lease->id}"), ['end_date' => $this->lease->start_date->copy()->subDay()->toDateString()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('end_date');

        $this->actingAs($this->landlord)
            ->putJson($this->apiUrl("/leases/{$this->lease->id}"), ['end_date' => $this->lease->end_date->copy()->subMonth()->toDateString()])
            ->assertStatus(200);
    }

    public function test_foreign_landlord_gets_403_without_start_date_of_foreign_lease(): void
    {
        $response = $this->actingAs($this->otherLandlord)
            ->putJson($this->apiUrl("/leases/{$this->lease->id}"), ['end_date' => '2000-01-01'])
            ->assertStatus(403);

        $this->assertStringNotContainsString($this->lease->start_date->toDateString(), $response->getContent());
    }

    public function test_everyone_who_sees_rent_sorts_by_it(): void
    {
        $this->lease->update(['rent_amount' => 30000]);
        Lease::factory()->create([
            'property_id' => $this->property->id,
            'rent_amount' => 5000,
            'start_date' => $this->lease->end_date->copy()->addDay(),
            'end_date' => null,
        ]);

        // nájemné vidí každý u smluv, které mu visibleTo vrátí (i přiřazený správce), takže řadit smí všichni
        foreach ([$this->landlord, $this->manager, $this->tenant] as $user) {
            $this->assertMatchesRegularExpression(self::RENT_SORT, $this->sortQuery($user));
        }
    }

    public function test_lease_list_query_count_does_not_depend_on_lease_count(): void
    {
        $few = $this->countListQueries($this->manager);
        Lease::factory()->count(5)->create(['property_id' => $this->property->id]);

        $this->assertSame($few, $this->countListQueries($this->manager));
    }

    /**
     * @param  list<Lease>  $leases
     */
    private function assertListed(User $user, array $leases): void
    {
        $ids = $this->actingAs($user)->getJson($this->apiUrl('/leases'))->assertStatus(200)->json('data.*.id');
        sort($ids);
        $expected = array_map(fn (Lease $lease) => $lease->id, $leases);
        sort($expected);

        $this->assertSame($expected, $ids, "smlouvy uživatele {$user->email}");
    }

    private function assertHasFinance(TestResponse $response): void
    {
        foreach (self::FINANCE_FIELDS as $field) {
            $this->assertArrayHasKey($field, $response->json(), "{$field} chybí");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $tenant, ?DateTimeInterface $start = null, ?DateTimeInterface $end = null): array
    {
        $start ??= now()->addYears(3);

        return [
            'property_id' => $this->property->id,
            'tenant_id' => $tenant->id,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end?->format('Y-m-d'),
            'rent_amount' => 12000,
        ];
    }

    private function sortQuery(User $user): string
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->getJson($this->apiUrl('/leases?sort=-rent_amount'))->assertStatus(200);
        $sql = implode("\n", array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();

        return $sql;
    }

    private function countListQueries(User $user): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->getJson($this->apiUrl('/leases'))->assertStatus(200);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
