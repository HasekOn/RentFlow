<?php

namespace Tests\Feature;

use App\Enums\RatingCategory;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\PropertyRoles;
use Tests\TestCase;

class RatingTest extends TestCase
{
    use PropertyRoles, RefreshDatabase;

    public function test_landlord_cannot_rate_active_lease(): void
    {
        [$landlord, $lease] = $this->leaseOfLandlord(Lease::factory());

        $this->actingAs($landlord)
            ->postJson($this->apiUrl("/leases/{$lease->id}/ratings"), ['category' => 'overall', 'score' => 4])
            ->assertStatus(422);

        $this->assertDatabaseMissing('ratings', ['lease_id' => $lease->id]);
    }

    public function test_landlord_can_rate_ended_lease(): void
    {
        [$landlord, $lease] = $this->leaseOfLandlord(Lease::factory()->ended());

        $this->actingAs($landlord)
            ->postJson($this->apiUrl("/leases/{$lease->id}/ratings"), ['category' => 'overall', 'score' => 4])
            ->assertStatus(201)
            ->assertJsonPath('category', 'overall');
    }

    public function test_only_property_owner_rates_and_deletes_ratings(): void
    {
        $this->setUpPropertyRoles();
        $url = $this->apiUrl("/leases/{$this->endedLease->id}/ratings");
        $payload = ['category' => RatingCategory::Overall->value, 'score' => 2];

        foreach ([$this->otherLandlord, $this->manager, $this->formerTenant] as $user) {
            $this->actingAs($user)->postJson($url, $payload)->assertStatus(403);
        }
        $this->assertDatabaseMissing('ratings', ['lease_id' => $this->endedLease->id]);

        $rating = Rating::factory()->create(['lease_id' => $this->endedLease->id, 'rated_by' => $this->landlord->id]);
        foreach ([$this->otherLandlord, $this->manager, $this->formerTenant] as $user) {
            $this->actingAs($user)->deleteJson($this->apiUrl("/ratings/{$rating->id}"))->assertStatus(403);
        }
        $this->actingAs($this->landlord)->deleteJson($this->apiUrl("/ratings/{$rating->id}"))->assertStatus(200);
        $this->actingAs($this->landlord)->deleteJson($this->apiUrl('/ratings/99999'))->assertStatus(404);

        // hodnocení smazané smlouvy vlastník smaže taky
        $kept = Rating::factory()->create(['lease_id' => $this->endedLease->id, 'rated_by' => $this->landlord->id]);
        $this->endedLease->delete();
        $this->actingAs($this->otherLandlord)->deleteJson($this->apiUrl("/ratings/{$kept->id}"))->assertStatus(403);
        $this->actingAs($this->landlord)->deleteJson($this->apiUrl("/ratings/{$kept->id}"))->assertStatus(200);
    }

    public function test_ratings_are_visible_to_whoever_sees_the_lease(): void
    {
        $this->setUpPropertyRoles();
        Rating::factory()->create(['lease_id' => $this->endedLease->id, 'rated_by' => $this->landlord->id]);
        $url = $this->apiUrl("/leases/{$this->endedLease->id}/ratings");

        $this->actingAs($this->landlord)->getJson($url)->assertStatus(200)->assertJsonCount(1);
        $this->actingAs($this->formerTenant)->getJson($url)->assertStatus(200)->assertJsonCount(1);
        // ukončenou smlouvu správce nevidí (Lease::visibleTo), cizí nikdo
        foreach ([$this->manager, $this->otherLandlord, $this->tenant] as $user) {
            $this->actingAs($user)->getJson($url)->assertStatus(403);
        }
    }

    /**
     * @return array{User, Lease}
     */
    private function leaseOfLandlord(Factory $factory): array
    {
        $landlord = User::factory()->landlord()->create();
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);

        return [$landlord, $factory->create(['property_id' => $property->id])];
    }
}
