<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingTest extends TestCase
{
    use RefreshDatabase;

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
