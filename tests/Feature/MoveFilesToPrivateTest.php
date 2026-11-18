<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketImage;
use App\Support\PrivateFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MoveFilesToPrivateTest extends TestCase
{
    use RefreshDatabase;

    public function test_moves_user_files_to_private_disk_and_is_idempotent(): void
    {
        $public = Storage::fake('public');
        $private = Storage::fake(PrivateFile::DISK);

        $property = Property::factory()->create();
        $ticket = Ticket::factory()->create(['property_id' => $property->id]);
        $paths = [
            'documents/1/smlouva.pdf',
            'properties/1/obyvak.jpg',
            'tickets/1/images/kohoutek.jpg',
            'tickets/1/priloha.png',
        ];
        foreach ($paths as $path) {
            $public->put($path, 'obsah '.$path);
        }
        $orphan = 'documents/1/sirotek-bez-zaznamu.pdf';
        $public->put($orphan, 'sirotek');

        $document = Document::query()->create([
            'property_id' => $property->id, 'document_type' => 'contract', 'name' => 'Smlouva',
            'file_path' => $paths[0], 'uploaded_by' => $property->landlord_id,
        ]);
        $document->delete(); // i soft-deleted dokument má soubor, který nesmí zůstat veřejně
        PropertyImage::factory()->create(['property_id' => $property->id, 'image_path' => $paths[1]]);
        TicketImage::query()->create(['ticket_id' => $ticket->id, 'uploaded_by' => $ticket->tenant_id, 'image_path' => $paths[2]]);
        TicketComment::factory()->create(['ticket_id' => $ticket->id, 'attachment_path' => $paths[3]]);
        TicketComment::factory()->create(['ticket_id' => $ticket->id, 'attachment_path' => 'tickets/1/chybi.png']);

        $public->put('contracts/contract_lease_1.pdf', 'pdf');
        $lease = Lease::factory()->create(['property_id' => $property->id]);
        $lease->forceFill(['contract_path' => 'contracts/contract_lease_1.pdf'])->save();

        $this->artisan('rentflow:move-files-to-private')
            ->expectsOutput('Moved: 5, missing: 1')
            ->assertSuccessful();

        foreach ($paths as $path) {
            $private->assertExists($path);
            $this->assertSame('obsah '.$path, $private->get($path));
        }
        $private->assertExists($orphan);
        $this->assertSame([], $public->allFiles());
        $this->assertNull($lease->refresh()->contract_path);

        $this->artisan('rentflow:move-files-to-private')
            ->expectsOutput('Moved: 0, missing: 1')
            ->assertSuccessful();
        $this->assertSame('obsah '.$paths[0], $private->get($paths[0]));
    }
}
