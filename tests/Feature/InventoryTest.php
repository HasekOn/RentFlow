<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\PropertyRoles;
use Tests\TestCase;

/**
 * Inventář (#9): čte kdo vidí nemovitost, zapisuje jen vlastník.
 */
class InventoryTest extends TestCase
{
    use PropertyRoles, RefreshDatabase;

    private InventoryItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPropertyRoles();
        $this->item = InventoryItem::factory()->create(['property_id' => $this->property->id]);
    }

    public function test_owner_manages_inventory(): void
    {
        $this->assertCanRead($this->landlord, true);

        $this->actingAs($this->landlord)
            ->postJson($this->apiUrl("/properties/{$this->property->id}/inventory"), ['name' => 'Lednice'])
            ->assertStatus(201);
        $this->actingAs($this->landlord)
            ->putJson($this->apiUrl("/inventory/{$this->item->id}"), ['name' => 'Pračka'])
            ->assertStatus(200)
            ->assertJsonPath('name', 'Pračka');
        $this->actingAs($this->landlord)->deleteJson($this->apiUrl("/inventory/{$this->item->id}"))->assertStatus(200);
        $this->assertSoftDeleted($this->item);
    }

    public function test_assigned_manager_and_tenant_only_read(): void
    {
        foreach ([$this->manager, $this->tenant] as $user) {
            $this->assertCanRead($user, true);
            $this->assertNoWrites($user);
        }
    }

    public function test_outsiders_neither_read_nor_write(): void
    {
        foreach ($this->outsiders() as $user) {
            $this->assertCanRead($user, false);
            $this->assertNoWrites($user);
        }
    }

    public function test_missing_property_or_item_is_404(): void
    {
        $this->actingAs($this->landlord)->getJson($this->apiUrl('/properties/99999/inventory'))->assertStatus(404);
        $this->actingAs($this->landlord)->getJson($this->apiUrl('/inventory/99999'))->assertStatus(404);
        $this->actingAs($this->landlord)->putJson($this->apiUrl('/inventory/99999'), ['name' => 'X'])->assertStatus(404);
    }

    private function assertCanRead(User $user, bool $allowed): void
    {
        $status = $allowed ? 200 : 403;
        $this->actingAs($user)->getJson($this->apiUrl("/properties/{$this->property->id}/inventory"))->assertStatus($status);
        $this->actingAs($user)->getJson($this->apiUrl("/inventory/{$this->item->id}"))->assertStatus($status);
    }

    private function assertNoWrites(User $user): void
    {
        $this->actingAs($user)
            ->postJson($this->apiUrl("/properties/{$this->property->id}/inventory"), ['name' => 'Lednice'])
            ->assertStatus(403);
        $this->actingAs($user)
            ->putJson($this->apiUrl("/inventory/{$this->item->id}"), ['name' => 'Cizí'])
            ->assertStatus(403);
        $this->actingAs($user)->deleteJson($this->apiUrl("/inventory/{$this->item->id}"))->assertStatus(403);

        $this->assertNotSoftDeleted($this->item);
        $this->assertSame(1, InventoryItem::query()->count());
    }
}
