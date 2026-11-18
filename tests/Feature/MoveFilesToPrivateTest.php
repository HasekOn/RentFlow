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
use Illuminate\Filesystem\FilesystemAdapter;
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
            ->expectsOutput('Moved: 5, already private: 0, conflicts: 0, failed: 0, missing: 1')
            ->assertSuccessful();

        foreach ($paths as $path) {
            $private->assertExists($path);
            $this->assertSame('obsah '.$path, $private->get($path));
        }
        $private->assertExists($orphan);
        $this->assertSame([], $public->allFiles());
        $this->assertNull($lease->refresh()->contract_path);

        $this->artisan('rentflow:move-files-to-private')
            ->expectsOutput('Moved: 0, already private: 0, conflicts: 0, failed: 0, missing: 1')
            ->assertSuccessful();
        $this->assertSame('obsah '.$paths[0], $private->get($paths[0]));
    }

    public function test_same_private_copy_is_reused_and_different_one_is_kept_as_conflict(): void
    {
        $public = Storage::fake('public');
        $private = Storage::fake(PrivateFile::DISK);
        $public->put('documents/1/stejny.pdf', 'stejny obsah');
        $private->put('documents/1/stejny.pdf', 'stejny obsah');
        $public->put('documents/1/jiny.pdf', 'verejna verze');
        $private->put('documents/1/jiny.pdf', 'privatni verze');

        $this->artisan('rentflow:move-files-to-private')
            ->expectsOutputToContain('Conflict, different private file kept, public file left in place: documents/1/jiny.pdf')
            ->expectsOutput('Moved: 0, already private: 1, conflicts: 1, failed: 0, missing: 0')
            ->assertFailed();

        $public->assertMissing('documents/1/stejny.pdf');
        $this->assertSame('verejna verze', $public->get('documents/1/jiny.pdf'));
        $this->assertSame('privatni verze', $private->get('documents/1/jiny.pdf'));
    }

    public function test_failed_write_keeps_public_file_and_fails(): void
    {
        $public = Storage::fake('public');
        $fake = Storage::fake(PrivateFile::DISK);
        $public->put('tickets/1/priloha.png', 'obsah');
        // disk s 'throw' => false: selhaný zápis jen vrátí false
        Storage::set(PrivateFile::DISK, new class($fake->getDriver(), $fake->getAdapter(), $fake->getConfig()) extends FilesystemAdapter
        {
            public function writeStream($path, $resource, array $options = [])
            {
                return false;
            }
        });

        $this->artisan('rentflow:move-files-to-private')
            ->expectsOutputToContain('Failed, public file left in place: tickets/1/priloha.png')
            ->expectsOutput('Moved: 0, already private: 0, conflicts: 0, failed: 1, missing: 0')
            ->assertFailed();

        $this->assertSame('obsah', $public->get('tickets/1/priloha.png'));
        $fake->assertMissing('tickets/1/priloha.png');
    }

    public function test_soft_deleted_document_without_file_is_not_reported_missing(): void
    {
        Storage::fake('public');
        Storage::fake(PrivateFile::DISK);
        $property = Property::factory()->create();
        Document::query()->create([
            'property_id' => $property->id, 'document_type' => 'contract', 'name' => 'Smazany',
            'file_path' => 'documents/1/smazany.pdf', 'uploaded_by' => $property->landlord_id,
        ])->delete();

        $this->artisan('rentflow:move-files-to-private')
            ->expectsOutput('Moved: 0, already private: 0, conflicts: 0, failed: 0, missing: 0')
            ->assertSuccessful();
    }
}
