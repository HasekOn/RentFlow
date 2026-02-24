<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private Property $property;

    private Lease $lease;

    public function test_landlord_can_get_dashboard_stats(): void
    {
        $response = $this->actingAs($this->landlord)->getJson($this->apiUrl('/dashboard/stats'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'properties' => ['total', 'occupied', 'available', 'renovation'],
                'finance' => ['monthly_income', 'monthly_expenses', 'cashflow', 'overdue_payments'],
                'leases' => ['active', 'expiring_soon'],
                'tickets' => ['open'],
            ]);
    }

    public function test_stats_counts_properties_correctly(): void
    {
        Property::factory()->create([
            'landlord_id' => $this->landlord->id,
            'status' => 'available',
        ]);
        Property::factory()->create([
            'landlord_id' => $this->landlord->id,
            'status' => 'renovation',
        ]);

        $response = $this->actingAs($this->landlord)->getJson($this->apiUrl('/dashboard/stats'));

        $response->assertStatus(200)
            ->assertJsonPath('properties.total', 3)
            ->assertJsonPath('properties.occupied', 1)
            ->assertJsonPath('properties.available', 1)
            ->assertJsonPath('properties.renovation', 1);
    }

    public function test_stats_counts_expiring_leases(): void
    {
        $response = $this->actingAs($this->landlord)->getJson($this->apiUrl('/dashboard/stats'));

        // Lease ends in 15 days — should be counted as expiring
        $response->assertJsonPath('leases.expiring_soon', 1);
    }

    public function test_stats_counts_overdue_payments(): void
    {
        Payment::factory()->unpaid()->create([
            'lease_id' => $this->lease->id,
            'due_date' => now()->subDays(10),
        ]);
        Payment::factory()->unpaid()->create([
            'lease_id' => $this->lease->id,
            'due_date' => now()->subDays(5),
        ]);
        Payment::factory()->paid()->create([
            'lease_id' => $this->lease->id,
            'due_date' => now()->subDays(3),
        ]);

        $response = $this->actingAs($this->landlord)->getJson($this->apiUrl('/dashboard/stats'));

        // 2 unpaid + past due, 1 paid = 2 overdue
        $response->assertJsonPath('finance.overdue_payments', 2);
    }

    public function test_stats_counts_open_tickets(): void
    {
        Ticket::factory()->open()->count(2)->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
        ]);
        Ticket::factory()->resolved()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->landlord)->getJson($this->apiUrl('/dashboard/stats'));

        $response->assertJsonPath('tickets.open', 2);
    }

    public function test_stats_calculates_monthly_cashflow(): void
    {
        Payment::factory()->create([
            'lease_id' => $this->lease->id,
            'status' => 'paid',
            'amount' => 15000,
            'paid_date' => now(),
        ]);
        Expense::factory()->create([
            'property_id' => $this->property->id,
            'amount' => 5000,
            'expense_date' => now(),
        ]);

        $response = $this->actingAs($this->landlord)->getJson($this->apiUrl('/dashboard/stats'));

        $income = $response->json('finance.monthly_income');
        $expenses = $response->json('finance.monthly_expenses');
        $cashflow = $response->json('finance.cashflow');

        $this->assertEquals(15000, (float) $income);
        $this->assertEquals(5000, (float) $expenses);
        $this->assertEquals(10000, (float) $cashflow);
    }

    public function test_tenant_cannot_access_dashboard(): void
    {
        $response = $this->actingAs($this->tenant)->getJson($this->apiUrl('/dashboard/stats'));

        $response->assertStatus(403);
    }

    public function test_landlord_can_get_finance_chart(): void
    {
        $response = $this->actingAs($this->landlord)->getJson($this->apiUrl('/dashboard/finance-chart'));

        $response->assertStatus(200)
            ->assertJsonCount(12);

        $firstMonth = $response->json()[0];
        $this->assertArrayHasKey('month', $firstMonth);
        $this->assertArrayHasKey('label', $firstMonth);
        $this->assertArrayHasKey('income', $firstMonth);
        $this->assertArrayHasKey('expenses', $firstMonth);
        $this->assertArrayHasKey('cashflow', $firstMonth);
    }

    public function test_tenant_cannot_access_finance_chart(): void
    {
        $response = $this->actingAs($this->tenant)->getJson($this->apiUrl('/dashboard/finance-chart'));

        $response->assertStatus(403);
    }

    public function test_landlord_can_get_occupancy_chart(): void
    {
        Property::factory()->create([
            'landlord_id' => $this->landlord->id,
            'status' => 'available',
        ]);

        $response = $this->actingAs($this->landlord)->getJson($this->apiUrl('/dashboard/occupancy-chart'));

        $response->assertStatus(200)
            ->assertJsonCount(3);

        $labels = collect($response->json())->pluck('label')->toArray();
        $this->assertEquals(['Occupied', 'Available', 'Renovation'], $labels);
    }

    public function test_landlord_can_get_tenant_trust_score(): void
    {
        $dueDate = now()->subMonths(3);
        for ($i = 0; $i < 3; $i++) {
            Payment::factory()->create([
                'lease_id' => $this->lease->id,
                'type' => 'rent',
                'due_date' => $dueDate->copy()->addMonths($i),
                'paid_date' => $dueDate->copy()->addMonths($i),
                'status' => 'paid',
            ]);
        }

        $response = $this->actingAs($this->landlord)->getJson($this->apiUrl('/tenants/'.$this->tenant->id.'/trust-score'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'tenant_id',
                'tenant_name',
                'trust_score',
                'breakdown' => [
                    'total_payments',
                    'on_time_payments',
                    'payment_ratio',
                    'average_rating',
                ],
            ]);

        $this->assertGreaterThan(0, $response->json('trust_score'));
    }

    public function test_trust_score_returns_404_for_nonexistent_tenant(): void
    {
        $response = $this->actingAs($this->landlord)->getJson($this->apiUrl('/tenants/99999/trust-score'));

        $response->assertStatus(404);
    }

    public function test_tenant_cannot_access_trust_score(): void
    {
        $response = $this->actingAs($this->tenant)->getJson($this->apiUrl('/tenants/'.$this->tenant->id.'/trust-score'));

        $response->assertStatus(403);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->landlord = User::factory()->landlord()->create();
        $this->tenant = User::factory()->tenant()->create();
        $this->property = Property::factory()->create([
            'landlord_id' => $this->landlord->id,
            'status' => 'occupied',
        ]);
        $this->lease = Lease::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
            'end_date' => now()->addDays(15),
        ]);
    }
}
