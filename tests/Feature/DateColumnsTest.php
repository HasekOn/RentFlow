<?php

namespace Tests\Feature;

use App\Enums\ExpenseType;
use App\Enums\PaymentStatus;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\PropertyRoles;
use Tests\TestCase;

/**
 * Data bez času a rozsahy (#27, #28, #29): sloupce date se ukládají jako půlnoc (cast DateOnly), rozsahy jsou
 * >= začátek a < začátek dalšího dne / měsíce, takže poslední den se vždy započítá.
 */
class DateColumnsTest extends TestCase
{
    use PropertyRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createPropertyRoles();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_date_columns_are_stored_without_time(): void
    {
        $this->actingAs($this->landlord)->postJson($this->apiUrl('/expenses'), [
            'property_id' => $this->property->id,
            'type' => ExpenseType::Repair->value,
            'amount' => 2500,
            'expense_date' => '2026-03-15 14:30:00',
        ])->assertStatus(201);
        $payment = Payment::factory()->create(['lease_id' => $this->lease->id, 'due_date' => Carbon::parse('2026-04-15 18:45:10')]);

        $this->assertSame('2026-03-15 00:00:00', DB::table('expenses')->value('expense_date'));
        $this->assertSame('2026-04-15 00:00:00', DB::table('payments')->where('id', $payment->id)->value('due_date'));
        $this->assertSame('2026-04-15', $payment->fresh()?->due_date?->toDateString());
        $this->assertSame(
            $this->lease->start_date->copy()->startOfDay()->format('Y-m-d H:i:s'),
            DB::table('leases')->where('id', $this->lease->id)->value('start_date'),
        );
    }

    public function test_date_to_filter_includes_the_last_day(): void
    {
        Carbon::setTestNow('2026-11-19 15:00:00');
        $ticket = Ticket::factory()->create(['property_id' => $this->property->id, 'tenant_id' => $this->tenant->id]);
        $expense = Expense::factory()->create(['property_id' => $this->property->id, 'expense_date' => today()]);

        $this->actingAs($this->landlord)
            ->getJson($this->apiUrl('/tickets?date_from=2026-11-19&date_to=2026-11-19'))
            ->assertStatus(200)
            ->assertJsonPath('data.*.id', [$ticket->id]);
        $this->actingAs($this->landlord)
            ->getJson($this->apiUrl('/expenses?date_to=2026-11-19'))
            ->assertStatus(200)
            ->assertJsonPath('data.*.id', [$expense->id]);
        $this->actingAs($this->landlord)
            ->getJson($this->apiUrl('/expenses?date_to=2026-11-18'))
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_invalid_date_filter_is_rejected(): void
    {
        $this->actingAs($this->landlord)
            ->getJson($this->apiUrl('/expenses?date_from=zitra'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('date_from');
    }

    public function test_dashboard_counts_payments_from_the_last_day_of_the_month(): void
    {
        Carbon::setTestNow('2026-01-31 18:00:00');
        Payment::factory()->create([
            'lease_id' => $this->lease->id,
            'status' => PaymentStatus::Paid,
            'amount' => 1000,
            'paid_date' => today(),
        ]);
        Payment::factory()->create([
            'lease_id' => $this->lease->id,
            'status' => PaymentStatus::Paid,
            'amount' => 700,
            'paid_date' => Carbon::parse('2025-11-30'),
        ]);

        $this->actingAs($this->landlord)
            ->getJson($this->apiUrl('/dashboard/stats'))
            ->assertJsonPath('finance.monthly_income', 1000);

        $chart = collect($this->actingAs($this->landlord)->getJson($this->apiUrl('/dashboard/finance-chart'))->json());
        // 31. ledna minus dva měsíce nesmí přetéct do prosince (dřív byl prosinec v grafu dvakrát a listopad chyběl)
        $this->assertSame(12, $chart->pluck('month')->unique()->count());
        $this->assertSame('2026-01', $chart->last()['month']);
        $this->assertEquals(1000, $chart->firstWhere('month', '2026-01')['income']);
        $this->assertEquals(700, $chart->firstWhere('month', '2025-11')['income']);
    }

    public function test_migration_moves_old_values_to_midnight(): void
    {
        $payment = Payment::factory()->create(['lease_id' => $this->lease->id]);
        DB::table('payments')->where('id', $payment->id)->update([
            'due_date' => '2026-02-05 13:45:12',
            'paid_date' => '2026-02-06',
        ]);

        $migration = require database_path('migrations/2026_11_19_000001_normalize_date_columns.php');
        $migration->up();

        $row = DB::table('payments')->where('id', $payment->id)->first();
        $this->assertSame('2026-02-05 00:00:00', $row?->due_date);
        $this->assertSame('2026-02-06 00:00:00', $row?->paid_date);
    }

    public function test_property_without_size_and_disposition_can_be_created(): void
    {
        $this->actingAs($this->landlord)->postJson($this->apiUrl('/properties'), [
            'address' => 'Bez plochy 1, Praha',
            'city' => 'Praha',
        ])
            ->assertStatus(201)
            ->assertJsonPath('size', null)
            ->assertJsonPath('disposition', null);
    }
}
