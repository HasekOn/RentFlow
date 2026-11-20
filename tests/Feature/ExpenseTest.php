<?php

namespace Tests\Feature;

use App\Enums\ExpenseType;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\PropertyRoles;
use Tests\TestCase;

/**
 * Náklady (#16): finance vlastníka, správce ani nájemník je nevidí.
 */
class ExpenseTest extends TestCase
{
    use PropertyRoles, RefreshDatabase;

    private Expense $expense;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createPropertyRoles();
        $this->expense = Expense::factory()->create([
            'property_id' => $this->property->id,
            'invoice_path' => 'javascript:alert(1)',
        ]);
        Expense::factory()->create(['property_id' => $this->otherProperty->id]);
    }

    public function test_owner_manages_own_expenses_without_invoice_path(): void
    {
        $this->actingAs($this->landlord)
            ->getJson($this->apiUrl('/expenses'))
            ->assertStatus(200)
            ->assertJsonPath('data.*.id', [$this->expense->id])
            ->assertJsonMissingPath('data.0.invoice_path');
        $this->actingAs($this->landlord)
            ->getJson($this->apiUrl("/expenses/{$this->expense->id}"))
            ->assertStatus(200)
            ->assertJsonMissingPath('invoice_path');

        $this->actingAs($this->landlord)->postJson($this->apiUrl('/expenses'), $this->payload())->assertStatus(201);
        $this->actingAs($this->landlord)
            ->putJson($this->apiUrl("/expenses/{$this->expense->id}"), ['amount' => 1500])
            ->assertStatus(200);
        $this->actingAs($this->landlord)->deleteJson($this->apiUrl("/expenses/{$this->expense->id}"))->assertStatus(200);
        $this->assertSoftDeleted($this->expense);
    }

    public function test_other_landlord_sees_only_his_and_cannot_touch_foreign(): void
    {
        $ids = $this->actingAs($this->otherLandlord)->getJson($this->apiUrl('/expenses'))->assertStatus(200)->json('data.*.id');
        $this->assertNotContains($this->expense->id, $ids);

        $this->assertNoAccess($this->otherLandlord);
    }

    public function test_managers_and_tenants_have_no_access_to_expenses(): void
    {
        foreach ([$this->manager, $this->strangerManager, $this->tenant, $this->formerTenant] as $user) {
            $this->actingAs($user)->getJson($this->apiUrl('/expenses'))->assertStatus(403);
            $this->assertNoAccess($user);
        }
    }

    public function test_expense_list_query_count_does_not_depend_on_expense_count(): void
    {
        $few = $this->countListQueries();
        Expense::factory()->count(5)->create(['property_id' => $this->property->id]);

        $this->assertSame($few, $this->countListQueries());
    }

    private function assertNoAccess(User $user): void
    {
        $this->actingAs($user)->getJson($this->apiUrl("/expenses/{$this->expense->id}"))->assertStatus(403);
        $this->actingAs($user)->postJson($this->apiUrl('/expenses'), $this->payload())->assertStatus(403);
        $this->actingAs($user)->putJson($this->apiUrl("/expenses/{$this->expense->id}"), ['amount' => 1])->assertStatus(403);
        $this->actingAs($user)->deleteJson($this->apiUrl("/expenses/{$this->expense->id}"))->assertStatus(403);

        $this->assertNotSoftDeleted($this->expense);
        $this->assertSame(1, Expense::query()->where('property_id', $this->property->id)->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'property_id' => $this->property->id,
            'type' => ExpenseType::Repair->value,
            'amount' => 2500,
            'expense_date' => now()->toDateString(),
        ];
    }

    private function countListQueries(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->landlord)->getJson($this->apiUrl('/expenses'))->assertStatus(200);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
