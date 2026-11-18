<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\User;
use App\Support\PrivateFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PropertyImageTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake(PrivateFile::DISK);

        $this->landlord = User::factory()->landlord()->create();
        $this->property = Property::factory()->occupied()->create(['landlord_id' => $this->landlord->id]);
    }

    public function test_upload_is_private_and_resource_hides_path(): void
    {
        $response = $this->actingAs($this->landlord)
            ->postJson($this->apiUrl("/properties/{$this->property->id}/images"), [
                'image' => UploadedFile::fake()->image('obyvak.jpg'),
            ])
            ->assertStatus(201)
            ->assertJsonMissingPath('image_path');

        $image = PropertyImage::query()->findOrFail($response->json('id'));
        $this->assertSame(route('api.property-images.file', $image->id), $response->json('image_url'));
        Storage::disk(PrivateFile::DISK)->assertExists($image->image_path);
        Storage::disk('public')->assertMissing($image->image_path);
    }

    public function test_file_is_available_only_to_users_who_see_the_property(): void
    {
        $image = $this->makeImage();
        $url = $this->apiUrl("/property-images/{$image->id}/file");

        $tenant = User::factory()->tenant()->create();
        Lease::factory()->create(['property_id' => $this->property->id, 'tenant_id' => $tenant->id]);
        $manager = User::factory()->manager()->create();
        $this->property->managers()->attach($manager->id);

        foreach ([$this->landlord, $tenant, $manager] as $user) {
            $this->actingAs($user)->get($url)->assertStatus(200)->assertHeader('X-Content-Type-Options', 'nosniff');
        }

        $strangers = [
            User::factory()->landlord()->create(),
            User::factory()->tenant()->create(),
            User::factory()->manager()->create(),
        ];
        foreach ($strangers as $user) {
            $this->actingAs($user)->get($url)->assertStatus(403);
        }
    }

    public function test_upload_rejects_svg(): void
    {
        $this->actingAs($this->landlord)
            ->postJson($this->apiUrl("/properties/{$this->property->id}/images"), [
                'image' => UploadedFile::fake()->create('plan.svg', 1, 'image/svg+xml'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }

    public function test_deleting_image_removes_private_file(): void
    {
        $image = $this->makeImage();

        $this->actingAs($this->landlord)
            ->deleteJson($this->apiUrl("/property-images/{$image->id}"))
            ->assertStatus(200);

        Storage::disk(PrivateFile::DISK)->assertMissing($image->image_path);
    }

    private function makeImage(): PropertyImage
    {
        $path = "properties/{$this->property->id}/".uniqid().'.jpg';
        Storage::disk(PrivateFile::DISK)->put($path, 'jpg');

        return PropertyImage::factory()->create([
            'property_id' => $this->property->id,
            'uploaded_by' => $this->landlord->id,
            'image_path' => $path,
        ]);
    }
}
