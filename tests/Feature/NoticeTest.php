<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\PropertyRoles;
use Tests\TestCase;

/**
 * Oznámení: čte kdo vidí nemovitost (i přiřazený správce), zapisuje jen vlastník.
 */
class NoticeTest extends TestCase
{
    use PropertyRoles, RefreshDatabase;

    private Notice $notice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createPropertyRoles();
        $this->notice = Notice::factory()->create([
            'property_id' => $this->property->id,
            'created_by' => $this->landlord->id,
        ]);
    }

    public function test_owner_manages_notices(): void
    {
        $this->assertCanList($this->landlord, true);

        $this->actingAs($this->landlord)
            ->postJson($this->apiUrl("/properties/{$this->property->id}/notices"), ['title' => 'Voda', 'content' => 'Odstávka'])
            ->assertStatus(201)
            ->assertJsonPath('created_by.id', $this->landlord->id);
        $this->actingAs($this->landlord)
            ->putJson($this->apiUrl("/notices/{$this->notice->id}"), ['title' => 'Plyn'])
            ->assertStatus(200)
            ->assertJsonPath('title', 'Plyn');
        $this->actingAs($this->landlord)->deleteJson($this->apiUrl("/notices/{$this->notice->id}"))->assertStatus(200);
        $this->assertModelMissing($this->notice);
    }

    public function test_assigned_manager_and_tenant_only_read(): void
    {
        foreach ([$this->manager, $this->tenant] as $user) {
            $this->assertCanList($user, true);
            $this->assertNoWrites($user);
        }
    }

    public function test_outsiders_neither_read_nor_write(): void
    {
        foreach ($this->outsiders() as $user) {
            $this->assertCanList($user, false);
            $this->assertNoWrites($user);
        }
    }

    public function test_missing_notice_is_404(): void
    {
        $this->actingAs($this->landlord)->getJson($this->apiUrl('/properties/99999/notices'))->assertStatus(404);
        $this->actingAs($this->landlord)->deleteJson($this->apiUrl('/notices/99999'))->assertStatus(404);
    }

    private function assertCanList(User $user, bool $allowed): void
    {
        $this->actingAs($user)
            ->getJson($this->apiUrl("/properties/{$this->property->id}/notices"))
            ->assertStatus($allowed ? 200 : 403);
    }

    private function assertNoWrites(User $user): void
    {
        $this->actingAs($user)
            ->postJson($this->apiUrl("/properties/{$this->property->id}/notices"), ['title' => 'Cizí', 'content' => 'Text'])
            ->assertStatus(403);
        $this->actingAs($user)
            ->putJson($this->apiUrl("/notices/{$this->notice->id}"), ['title' => 'Cizí'])
            ->assertStatus(403);
        $this->actingAs($user)->deleteJson($this->apiUrl("/notices/{$this->notice->id}"))->assertStatus(403);

        $this->assertSame(1, Notice::query()->count());
    }
}
