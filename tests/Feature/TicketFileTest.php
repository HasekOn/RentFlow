<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Property;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketImage;
use App\Models\User;
use App\Support\PrivateFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketFileTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake(PrivateFile::DISK);

        $this->landlord = User::factory()->landlord()->create();
        $this->tenant = User::factory()->tenant()->create();
        $property = Property::factory()->occupied()->create(['landlord_id' => $this->landlord->id]);
        Lease::factory()->create(['property_id' => $property->id, 'tenant_id' => $this->tenant->id]);
        $this->ticket = Ticket::factory()->create(['property_id' => $property->id, 'tenant_id' => $this->tenant->id]);
    }

    public function test_ticket_image_is_private_and_downloadable_only_by_ticket_viewers(): void
    {
        $response = $this->actingAs($this->tenant)
            ->postJson($this->apiUrl("/tickets/{$this->ticket->id}/images"), [
                'image' => UploadedFile::fake()->image('kapajici-kohoutek.jpg'),
            ])
            ->assertStatus(201)
            ->assertJsonMissingPath('image_path');

        $image = TicketImage::query()->findOrFail($response->json('id'));
        $this->assertSame(route('api.ticket-images.file', [$this->ticket->id, $image->id]), $response->json('image_url'));
        Storage::disk(PrivateFile::DISK)->assertExists($image->image_path);
        Storage::disk('public')->assertMissing($image->image_path);
        $this->actingAs($this->tenant)
            ->getJson($this->apiUrl("/tickets/{$this->ticket->id}"))
            ->assertStatus(200)
            ->assertJsonMissingPath('images.0.image_path')
            ->assertJsonPath('images.0.image_url', $response->json('image_url'));

        $url = $this->apiUrl("/tickets/{$this->ticket->id}/images/{$image->id}/file");
        $this->actingAs($this->tenant)->get($url)->assertStatus(200)->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->landlord)->get($url)->assertStatus(200);
        $this->actingAs(User::factory()->tenant()->create())->get($url)->assertStatus(403);
        $this->actingAs(User::factory()->landlord()->create())->get($url)->assertStatus(403);
    }

    public function test_ticket_image_from_another_ticket_returns_404(): void
    {
        $image = $this->makeImage($this->ticket);
        $otherTicket = Ticket::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->tenant)
            ->get($this->apiUrl("/tickets/{$otherTicket->id}/images/{$image->id}/file"))
            ->assertStatus(404);
    }

    public function test_ticket_image_rejects_svg(): void
    {
        $this->actingAs($this->tenant)
            ->postJson($this->apiUrl("/tickets/{$this->ticket->id}/images"), [
                'image' => UploadedFile::fake()->create('obrazek.svg', 1, 'image/svg+xml'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }

    public function test_deleting_ticket_image_removes_private_file(): void
    {
        $image = $this->makeImage($this->ticket);

        $this->actingAs($this->tenant)
            ->deleteJson($this->apiUrl("/tickets/{$this->ticket->id}/images/{$image->id}"))
            ->assertStatus(200);

        Storage::disk(PrivateFile::DISK)->assertMissing($image->image_path);
    }

    public function test_comment_attachment_is_private_and_downloadable_only_by_ticket_viewers(): void
    {
        $response = $this->actingAs($this->tenant)
            ->postJson($this->apiUrl("/tickets/{$this->ticket->id}/comments"), [
                'message' => 'Posílám fotku.',
                'attachment' => UploadedFile::fake()->image('fotka.png'),
            ])
            ->assertStatus(201)
            ->assertJsonMissingPath('attachment_path');

        $comment = TicketComment::query()->findOrFail($response->json('id'));
        Storage::disk(PrivateFile::DISK)->assertExists($comment->attachment_path);
        Storage::disk('public')->assertMissing($comment->attachment_path);

        $url = $this->apiUrl("/tickets/{$this->ticket->id}/comments/{$comment->id}/attachment");
        $this->assertSame(url($url), $response->json('attachment_url'));
        $this->actingAs($this->landlord)->get($url)->assertStatus(200);
        $this->actingAs(User::factory()->tenant()->create())->get($url)->assertStatus(403);

        $this->actingAs($this->tenant)
            ->deleteJson($this->apiUrl("/tickets/{$this->ticket->id}/comments/{$comment->id}"))
            ->assertStatus(200);
        Storage::disk(PrivateFile::DISK)->assertMissing($comment->attachment_path);
    }

    public function test_comment_without_attachment_returns_404_and_rejects_svg(): void
    {
        $comment = TicketComment::factory()->create(['ticket_id' => $this->ticket->id, 'user_id' => $this->tenant->id]);

        $this->actingAs($this->tenant)
            ->get($this->apiUrl("/tickets/{$this->ticket->id}/comments/{$comment->id}/attachment"))
            ->assertStatus(404);

        $this->actingAs($this->tenant)
            ->postJson($this->apiUrl("/tickets/{$this->ticket->id}/comments"), [
                'message' => 'SVG',
                'attachment' => UploadedFile::fake()->create('obrazek.svg', 1, 'image/svg+xml'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('attachment');
    }

    private function makeImage(Ticket $ticket): TicketImage
    {
        $path = "tickets/{$ticket->id}/images/".uniqid().'.jpg';
        Storage::disk(PrivateFile::DISK)->put($path, 'jpg');

        return TicketImage::query()->create([
            'ticket_id' => $ticket->id,
            'uploaded_by' => $this->tenant->id,
            'image_path' => $path,
        ]);
    }
}
