<?php

namespace Tests\Feature;

use App\Enums\DocumentVisibility;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Lease;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_cannot_change_role_or_trust_score(): void
    {
        $tenant = User::factory()->tenant()->create();
        $score = $tenant->trust_score;

        $this->actingAs($tenant)->putJson($this->apiUrl('/profile'), [
            'name' => 'Nové jméno',
            'role' => 'landlord',
            'trust_score' => 100,
        ])->assertStatus(200);

        $tenant->refresh();
        $this->assertSame('Nové jméno', $tenant->name);
        $this->assertSame(Role::Tenant, $tenant->role);
        $this->assertSame($score, $tenant->trust_score);
    }

    public function test_landlord_can_promote_and_demote_manager(): void
    {
        $landlord = User::factory()->landlord()->create();
        $user = User::factory()->tenant()->create();
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);

        $this->actingAs($landlord)
            ->postJson($this->apiUrl("/users/{$user->id}/promote-manager"))
            ->assertStatus(200)
            ->assertJsonPath('user.role', 'manager');
        $this->assertSame(Role::Manager, $user->refresh()->role);

        $property->managers()->attach($user->id);

        $this->actingAs($landlord)
            ->postJson($this->apiUrl("/users/{$user->id}/demote-manager"))
            ->assertStatus(200)
            ->assertJsonPath('user.role', 'tenant');
        $this->assertSame(Role::Tenant, $user->refresh()->role);
        $this->assertDatabaseMissing('property_manager', ['user_id' => $user->id]);
    }

    public function test_tenant_cannot_promote_and_landlord_cannot_be_promoted(): void
    {
        $landlord = User::factory()->landlord()->create();
        $otherLandlord = User::factory()->landlord()->create();
        $tenant = User::factory()->tenant()->create();

        $this->actingAs($tenant)
            ->postJson($this->apiUrl("/users/{$tenant->id}/promote-manager"))
            ->assertStatus(403);

        $this->actingAs($landlord)
            ->postJson($this->apiUrl("/users/{$otherLandlord->id}/promote-manager"))
            ->assertStatus(422);

        $this->actingAs($landlord)
            ->postJson($this->apiUrl('/users/999999/promote-manager'))
            ->assertStatus(404);
    }

    public function test_role_middleware_does_not_reveal_required_roles(): void
    {
        $tenant = User::factory()->tenant()->create();

        $response = $this->actingAs($tenant)->postJson($this->apiUrl('/expenses'), []);

        $response->assertStatus(403)->assertExactJson(['message' => 'Access denied.']);
    }

    public function test_document_visibility_is_respected_per_role(): void
    {
        Storage::fake('public');
        $landlord = User::factory()->landlord()->create();
        $tenant = User::factory()->tenant()->create();
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);
        Lease::factory()->create(['property_id' => $property->id, 'tenant_id' => $tenant->id]);

        $shared = $this->document($property, DocumentVisibility::LandlordTenant);
        $private = $this->document($property, DocumentVisibility::LandlordOnly);

        $this->actingAs($tenant)
            ->getJson($this->apiUrl("/properties/{$property->id}/documents"))
            ->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $shared->id);

        $this->actingAs($tenant)->get($this->apiUrl("/documents/{$shared->id}/download"))->assertStatus(200);
        $this->actingAs($tenant)->get($this->apiUrl("/documents/{$private->id}/download"))->assertStatus(403);
    }

    public function test_document_visibility_is_respected_for_assigned_manager(): void
    {
        Storage::fake('public');
        $landlord = User::factory()->landlord()->create();
        $manager = User::factory()->manager()->create();
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);
        $property->managers()->attach($manager->id);

        $forManager = $this->document($property, DocumentVisibility::LandlordManager);
        $forTenant = $this->document($property, DocumentVisibility::LandlordTenant);

        $this->actingAs($manager)
            ->getJson($this->apiUrl("/properties/{$property->id}/documents"))
            ->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $forManager->id);

        $this->actingAs($manager)->get($this->apiUrl("/documents/{$forManager->id}/download"))->assertStatus(200);
        $this->actingAs($manager)->get($this->apiUrl("/documents/{$forTenant->id}/download"))->assertStatus(403);
    }

    public function test_document_upload_rejects_unknown_visibility(): void
    {
        Storage::fake('public');
        $landlord = User::factory()->landlord()->create();
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);

        $this->actingAs($landlord)->postJson($this->apiUrl("/properties/{$property->id}/documents"), [
            'file' => UploadedFile::fake()->create('smlouva.pdf', 10, 'application/pdf'),
            'document_type' => 'contract',
            'name' => 'Smlouva',
            'visibility' => 'everyone',
        ])->assertStatus(422)->assertJsonValidationErrors('visibility');
    }

    private function document(Property $property, DocumentVisibility $visibility): Document
    {
        $path = "documents/{$property->id}/{$visibility->value}.pdf";
        Storage::disk('public')->put($path, 'pdf');

        return Document::query()->create([
            'property_id' => $property->id,
            'document_type' => 'contract',
            'name' => $visibility->value.'.pdf',
            'file_path' => $path,
            'visibility' => $visibility,
            'uploaded_by' => $property->landlord_id,
        ]);
    }
}
