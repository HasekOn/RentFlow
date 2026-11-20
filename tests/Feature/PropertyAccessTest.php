<?php

namespace Tests\Feature;

use App\Enums\LeaseStatus;
use App\Models\Lease;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kdo nemovitost vidí (Property::visibleTo), co z ní vidí (smlouvy, purchase_price) a kdo ji smaže a obnoví.
 */
class PropertyAccessTest extends TestCase
{
    use RefreshDatabase;

    private const string PRICE_SORT = '/order by\W+purchase_price\W/i';

    private User $landlord;

    private Property $property;

    private User $tenant;

    private User $neighbour;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->landlord = User::factory()->landlord()->create();
        $this->property = Property::factory()->occupied()->create(['landlord_id' => $this->landlord->id]);

        $this->tenant = User::factory()->tenant()->create();
        Lease::factory()->create(['property_id' => $this->property->id, 'tenant_id' => $this->tenant->id]);
        $this->neighbour = User::factory()->tenant()->create(); // druhá aktivní smlouva na stejné nemovitosti
        Lease::factory()->create(['property_id' => $this->property->id, 'tenant_id' => $this->neighbour->id]);

        $this->manager = User::factory()->manager()->create();
        $this->property->managers()->attach($this->manager->id);
    }

    public function test_visibility_by_relation_to_property(): void
    {
        $this->assertListedAndShown($this->landlord, true);
        $this->assertListedAndShown($this->manager, true);
        $this->assertListedAndShown($this->tenant, true);

        $formerTenant = User::factory()->tenant()->create();
        Lease::factory()->ended()->create(['property_id' => $this->property->id, 'tenant_id' => $formerTenant->id]);
        $strangers = [
            User::factory()->landlord()->create(),
            User::factory()->manager()->create(),
            User::factory()->tenant()->create(),
            $formerTenant,
        ];
        foreach ($strangers as $user) {
            $this->assertListedAndShown($user, false);
        }
    }

    public function test_manager_who_rents_elsewhere_sees_both_properties(): void
    {
        $rented = Property::factory()->occupied()->create();
        Lease::factory()->create(['property_id' => $rented->id, 'tenant_id' => $this->manager->id]);

        $ids = $this->actingAs($this->manager)->getJson($this->apiUrl('/properties'))->assertStatus(200)->json('data.*.id');
        sort($ids);

        $this->assertSame([$this->property->id, $rented->id], $ids);
    }

    public function test_only_owner_sees_all_leases_and_purchase_price(): void
    {
        $url = $this->apiUrl("/properties/{$this->property->id}");

        $this->actingAs($this->landlord)->getJson($url)
            ->assertJsonCount(2, 'leases')
            ->assertJsonPath('purchase_price', $this->property->purchase_price);

        $this->actingAs($this->tenant)->getJson($url)
            ->assertJsonCount(1, 'leases')
            ->assertJsonPath('leases.0.tenant.id', $this->tenant->id)
            ->assertJsonMissingPath('purchase_price');
        $this->actingAs($this->tenant)->getJson($this->apiUrl('/properties'))
            ->assertJsonCount(1, 'data.0.leases')
            ->assertJsonMissingPath('data.0.purchase_price');

        // správce od 2.8 vidí aktivní smlouvy (Lease::visibleTo) s nájemným pro párování plateb, kauci ne; dřív žádnou cizí
        $this->actingAs($this->manager)->getJson($url)
            ->assertJsonCount(2, 'leases')
            ->assertJsonStructure(['leases' => [['rent_amount', 'variable_symbol']]])
            ->assertJsonMissingPath('leases.0.deposit_amount')
            ->assertJsonMissingPath('purchase_price');
    }

    public function test_only_owner_sorts_by_purchase_price(): void
    {
        $this->property->update(['purchase_price' => 2000000]);
        $cheap = Property::factory()->create(['landlord_id' => $this->landlord->id, 'purchase_price' => 1000000]);
        $expensive = Property::factory()->create(['landlord_id' => $this->landlord->id, 'purchase_price' => 3000000]);
        $cheap->managers()->attach($this->manager->id);
        $expensive->managers()->attach($this->manager->id);

        $this->actingAs($this->landlord)
            ->getJson($this->apiUrl('/properties?sort=-purchase_price'))
            ->assertJsonPath('data.*.id', [$expensive->id, $this->property->id, $cheap->id]);
        $this->assertMatchesRegularExpression(self::PRICE_SORT, $this->sortQuery($this->landlord));

        // správce cenu nevidí, takže podle ní ani neřadí (pořadí by ceny prozradilo)
        $this->assertDoesNotMatchRegularExpression(self::PRICE_SORT, $this->sortQuery($this->manager));
    }

    public function test_list_parameters_sent_as_array_are_ignored_not_500(): void
    {
        $this->actingAs($this->landlord)
            ->getJson($this->apiUrl('/properties?sort[]=-purchase_price&search[]=x&status[]=occupied&date_from[]=2020-01-01'))
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $this->property->id);
    }

    public function test_property_with_active_lease_cannot_be_deleted(): void
    {
        $url = $this->apiUrl("/properties/{$this->property->id}");

        $this->actingAs($this->landlord)->deleteJson($url)
            ->assertStatus(422)
            ->assertJsonPath('errors.property.0', 'Property with an active lease cannot be deleted.');
        $this->assertNotSoftDeleted($this->property);

        $this->property->leases()->update(['status' => LeaseStatus::Ended]);
        $this->actingAs($this->landlord)->deleteJson($url)->assertStatus(200);
        $this->assertSoftDeleted($this->property);
    }

    public function test_only_owner_restores_property(): void
    {
        $property = Property::factory()->create(['landlord_id' => $this->landlord->id]);
        $property->delete();
        $url = $this->apiUrl("/properties/{$property->id}/restore");

        foreach ([User::factory()->landlord()->create(), $this->manager, $this->tenant] as $user) {
            $this->actingAs($user)->putJson($url)->assertStatus(403);
        }
        $this->assertSoftDeleted($property);

        $this->actingAs($this->landlord)->putJson($url)->assertStatus(200);
        $this->assertNotSoftDeleted($property);
        $this->actingAs($this->landlord)->putJson($this->apiUrl('/properties/99999/restore'))->assertStatus(404);
    }

    public function test_only_owner_lists_property_managers(): void
    {
        $url = $this->apiUrl("/properties/{$this->property->id}/managers");

        $this->actingAs($this->landlord)->getJson($url)->assertStatus(200)->assertJsonCount(1);
        foreach ([$this->manager, $this->tenant, User::factory()->landlord()->create()] as $user) {
            $this->actingAs($user)->getJson($url)->assertStatus(403);
        }
        $this->actingAs($this->landlord)->getJson($this->apiUrl('/properties/99999/managers'))->assertStatus(404);
    }

    public function test_property_list_query_count_does_not_depend_on_property_count(): void
    {
        $few = $this->countListQueries();
        foreach (Property::factory()->count(5)->create(['landlord_id' => $this->landlord->id]) as $property) {
            Lease::factory()->create(['property_id' => $property->id]);
            $property->managers()->attach($this->manager->id);
        }

        $this->assertSame($few, $this->countListQueries());
    }

    private function assertListedAndShown(User $user, bool $visible): void
    {
        $ids = $this->actingAs($user)->getJson($this->apiUrl('/properties'))->assertStatus(200)->json('data.*.id');
        $this->assertSame($visible, in_array($this->property->id, $ids, true));

        $this->actingAs($user)
            ->getJson($this->apiUrl("/properties/{$this->property->id}"))
            ->assertStatus($visible ? 200 : 403);
    }

    private function countListQueries(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->landlord)->getJson($this->apiUrl('/properties'))->assertStatus(200);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function sortQuery(User $user): string
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->getJson($this->apiUrl('/properties?sort=-purchase_price'))->assertStatus(200);
        $sql = implode("\n", array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();

        return $sql;
    }
}
