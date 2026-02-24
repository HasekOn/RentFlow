<?php

namespace Tests\Unit;

use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Rating;
use App\Models\User;
use App\Services\TrustScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustScoreTest extends TestCase
{
    use RefreshDatabase;

    private TrustScoreService $service;

    private User $landlord;

    private User $tenant;

    private Lease $lease;

    public function test_new_tenant_without_payments_gets_default_score(): void
    {
        $score = $this->service->calculate($this->tenant);

        $this->assertEquals(50.0, $score);
    }

    public function test_all_payments_on_time_gives_high_score(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $dueDate = now()->subMonths($i)->startOfMonth()->addDays(14);
            Payment::factory()->create([
                'lease_id' => $this->lease->id,
                'type' => 'rent',
                'due_date' => $dueDate,
                'paid_date' => $dueDate->copy()->subDays(1), // Paid 1 day early
                'status' => 'paid',
            ]);
        }

        $score = $this->service->calculate($this->tenant);

        $this->assertEquals(100.0, $score);
    }

    public function test_all_payments_late_gives_low_score(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $dueDate = now()->subMonths($i)->startOfMonth()->addDays(14);
            Payment::factory()->create([
                'lease_id' => $this->lease->id,
                'type' => 'rent',
                'due_date' => $dueDate,
                'paid_date' => $dueDate->copy()->addDays(20), // 20 days late
                'status' => 'paid',
            ]);
        }

        $score = $this->service->calculate($this->tenant);

        $this->assertEquals(0.0, $score);
    }

    public function test_unpaid_payments_hurt_score(): void
    {
        Payment::factory()->unpaid()->create([
            'lease_id' => $this->lease->id,
            'type' => 'rent',
            'due_date' => now()->subMonth(),
        ]);

        $score = $this->service->calculate($this->tenant);

        $this->assertEquals(0.0, $score);
    }

    public function test_mixed_payments_give_middle_score(): void
    {
        // 3 on time
        for ($i = 3; $i <= 5; $i++) {
            $dueDate = now()->subMonths($i)->startOfMonth()->addDays(14);
            Payment::factory()->create([
                'lease_id' => $this->lease->id,
                'type' => 'rent',
                'due_date' => $dueDate,
                'paid_date' => $dueDate,
                'status' => 'paid',
            ]);
        }

        // 2 late (10 days)
        for ($i = 1; $i <= 2; $i++) {
            $dueDate = now()->subMonths($i)->startOfMonth()->addDays(14);
            Payment::factory()->create([
                'lease_id' => $this->lease->id,
                'type' => 'rent',
                'due_date' => $dueDate,
                'paid_date' => $dueDate->copy()->addDays(10),
                'status' => 'paid',
            ]);
        }

        $score = $this->service->calculate($this->tenant);

        $this->assertEquals(80.0, $score);
    }

    public function test_only_rent_payments_count(): void
    {
        // On-time rent payment
        $dueDate = now()->subMonth()->startOfMonth()->addDays(14);
        Payment::factory()->create([
            'lease_id' => $this->lease->id,
            'type' => 'rent',
            'due_date' => $dueDate,
            'paid_date' => $dueDate,
            'status' => 'paid',
        ]);

        // Late utility payment — should NOT affect score
        Payment::factory()->create([
            'lease_id' => $this->lease->id,
            'type' => 'utilities',
            'due_date' => now()->subMonth(),
            'paid_date' => now(),
            'status' => 'paid',
        ]);

        $score = $this->service->calculate($this->tenant);

        $this->assertEquals(100.0, $score);
    }

    public function test_ratings_affect_score(): void
    {
        // Perfect on-time payment
        $dueDate = now()->subMonth()->startOfMonth()->addDays(14);
        Payment::factory()->create([
            'lease_id' => $this->lease->id,
            'type' => 'rent',
            'due_date' => $dueDate,
            'paid_date' => $dueDate,
            'status' => 'paid',
        ]);

        // Low ratings (all 2/5)
        foreach (['apartment_condition', 'communication', 'rules', 'overall'] as $category) {
            Rating::factory()->create([
                'lease_id' => $this->lease->id,
                'rated_by' => $this->landlord->id,
                'category' => $category,
                'score' => 2,
            ]);
        }

        $score = $this->service->calculate($this->tenant);

        // Payment = 100, Rating = 2*20 = 40
        // Total = (100 * 0.6) + (40 * 0.4) = 60 + 16 = 76
        $this->assertEquals(76.0, $score);
    }

    public function test_perfect_ratings_and_payments(): void
    {
        $dueDate = now()->subMonth()->startOfMonth()->addDays(14);
        Payment::factory()->create([
            'lease_id' => $this->lease->id,
            'type' => 'rent',
            'due_date' => $dueDate,
            'paid_date' => $dueDate,
            'status' => 'paid',
        ]);

        foreach (['apartment_condition', 'communication', 'rules', 'overall'] as $category) {
            Rating::factory()->create([
                'lease_id' => $this->lease->id,
                'rated_by' => $this->landlord->id,
                'category' => $category,
                'score' => 5,
            ]);
        }

        $score = $this->service->calculate($this->tenant);

        // Payment = 100, Rating = 5*20 = 100
        // Total = (100 * 0.6) + (100 * 0.4) = 60 + 40 = 100
        $this->assertEquals(100.0, $score);
    }

    public function test_without_ratings_uses_only_payment_score(): void
    {
        $dueDate = now()->subMonth()->startOfMonth()->addDays(14);
        Payment::factory()->create([
            'lease_id' => $this->lease->id,
            'type' => 'rent',
            'due_date' => $dueDate,
            'paid_date' => $dueDate,
            'status' => 'paid',
        ]);

        $score = $this->service->calculate($this->tenant);

        // No ratings → 100% weight on payments → 100
        $this->assertEquals(100.0, $score);
    }

    public function test_score_is_saved_to_user(): void
    {
        $dueDate = now()->subMonth()->startOfMonth()->addDays(14);
        Payment::factory()->create([
            'lease_id' => $this->lease->id,
            'type' => 'rent',
            'due_date' => $dueDate,
            'paid_date' => $dueDate,
            'status' => 'paid',
        ]);

        $this->service->calculate($this->tenant);

        $this->tenant->refresh();
        $this->assertEquals(100.0, (float) $this->tenant->trust_score);
    }

    public function test_score_is_clamped_between_0_and_100(): void
    {
        // Many unpaid payments
        for ($i = 1; $i <= 10; $i++) {
            Payment::factory()->unpaid()->create([
                'lease_id' => $this->lease->id,
                'type' => 'rent',
                'due_date' => now()->subMonths($i),
            ]);
        }

        $score = $this->service->calculate($this->tenant);

        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TrustScoreService;
        $this->landlord = User::factory()->landlord()->create();
        $this->tenant = User::factory()->tenant()->create();
        $property = Property::factory()->create(['landlord_id' => $this->landlord->id]);
        $this->lease = Lease::factory()->create([
            'property_id' => $property->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }
}
