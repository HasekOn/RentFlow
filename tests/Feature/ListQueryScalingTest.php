<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedLikeData;
use Tests\TestCase;

/**
 * Výkon (#48): počet dotazů hlavních seznamů a přehledů nezávisí na množství dat. Data ve velikosti seedu,
 * pak seed × 10, pro pronajímatele, správce (zároveň nájemníka) a nájemníka.
 */
class ListQueryScalingTest extends TestCase
{
    use RefreshDatabase, SeedLikeData;

    public function test_main_lists_query_count_does_not_grow_with_data(): void
    {
        $this->createSeedLikeData();
        $users = ['landlord' => $this->seedLandlord, 'manager' => $this->seedManager, 'tenant' => $this->seedTenant];

        $seed = array_map(fn ($user) => $this->countMeasuredQueries($user), $users);
        for ($batch = 2; $batch <= 10; $batch++) {
            $this->addSeedBatch();
        }
        $tenTimes = array_map(fn ($user) => $this->countMeasuredQueries($user), $users);

        $this->assertSame($seed, $tenTimes);
    }

    public function test_landlord_reaches_every_measured_list(): void
    {
        $this->createSeedLikeData();

        foreach (self::MEASURED_URLS as $url) {
            $this->actingAs($this->seedLandlord)->getJson($this->apiUrl($url))->assertStatus(200);
        }
    }
}
