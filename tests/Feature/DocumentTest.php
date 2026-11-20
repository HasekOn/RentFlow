<?php

namespace Tests\Feature;

use App\Enums\DocumentVisibility;
use App\Models\Document;
use App\Models\Lease;
use App\Models\Property;
use App\Models\User;
use App\Support\PrivateFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private Property $property;

    /** @var array<string, Document> klíč = hodnota visibility */
    private array $documents = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake(PrivateFile::DISK);

        $this->landlord = User::factory()->landlord()->create();
        $this->property = Property::factory()->occupied()->create(['landlord_id' => $this->landlord->id]);

        foreach (DocumentVisibility::cases() as $visibility) {
            $this->documents[$visibility->value] = $this->makeDocument($this->property, $visibility);
        }
    }

    public function test_owner_sees_downloads_and_deletes_all_documents(): void
    {
        $this->assertVisibleIds($this->landlord, DocumentVisibility::cases());

        $private = $this->documents['landlord_only'];
        $this->actingAs($this->landlord)
            ->get($this->apiUrl("/documents/{$private->id}/download"))
            ->assertStatus(200)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertDownload($private->name);

        $this->actingAs($this->landlord)
            ->deleteJson($this->apiUrl("/documents/{$private->id}"))
            ->assertStatus(200);
        Storage::disk(PrivateFile::DISK)->assertMissing($private->file_path);
        $this->assertSoftDeleted($private);
    }

    public function test_download_name_is_built_on_server_with_stored_extension(): void
    {
        $cases = [
            ['Smlouva 1/2025', 'pdf', 'Smlouva 1-2025.pdf'],
            ['Fotka', 'png', 'Fotka.png'],
            ['Revize\\kotel.PDF', 'pdf', 'Revize-kotel.PDF'],
        ];

        foreach ($cases as [$name, $extension, $expected]) {
            $path = "documents/{$this->property->id}/".uniqid().'.'.$extension;
            Storage::disk(PrivateFile::DISK)->put($path, 'obsah');
            $document = Document::query()->create([
                'property_id' => $this->property->id, 'document_type' => 'other', 'name' => $name,
                'file_path' => $path, 'uploaded_by' => $this->landlord->id,
            ]);

            $this->actingAs($this->landlord)
                ->get($this->apiUrl("/documents/{$document->id}/download"))
                ->assertStatus(200)
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox")
                ->assertDownload($expected);
        }

        $extensions = $this->actingAs($this->landlord)
            ->getJson($this->apiUrl("/properties/{$this->property->id}/documents"))
            ->json('*.extension');
        $this->assertContains('png', $extensions);
        $this->assertContains('pdf', $extensions);
    }

    public function test_private_disk_has_no_storage_url(): void
    {
        $document = $this->documents['all'];

        $this->get('/storage/'.$document->file_path)->assertStatus(404);
        $this->actingAs($this->landlord)->get('/storage/'.$document->file_path)->assertStatus(404);
    }

    public function test_other_landlord_has_no_access(): void
    {
        $other = User::factory()->landlord()->create();
        $document = $this->documents['all'];

        $this->actingAs($other)->getJson($this->apiUrl("/properties/{$this->property->id}/documents"))->assertStatus(403);
        $this->actingAs($other)->get($this->apiUrl("/documents/{$document->id}/download"))->assertStatus(403);
        $this->actingAs($other)->deleteJson($this->apiUrl("/documents/{$document->id}"))->assertStatus(403);
        Storage::disk(PrivateFile::DISK)->assertExists($document->file_path);
    }

    public function test_assigned_manager_sees_manager_documents_but_cannot_delete(): void
    {
        $manager = User::factory()->manager()->create();
        $this->property->managers()->attach($manager->id);

        $this->assertVisibleIds($manager, [DocumentVisibility::LandlordManager, DocumentVisibility::All]);
        $this->assertDownloadStatus($manager, 'landlord_manager', 200);
        $this->assertDownloadStatus($manager, 'landlord_tenant', 403);
        $this->assertDownloadStatus($manager, 'landlord_only', 403);

        $this->actingAs($manager)
            ->deleteJson($this->apiUrl("/documents/{$this->documents['all']->id}"))
            ->assertStatus(403);
    }

    public function test_unassigned_manager_has_no_access(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->getJson($this->apiUrl("/properties/{$this->property->id}/documents"))
            ->assertStatus(403);
        $this->assertDownloadStatus($manager, 'all', 403);
    }

    public function test_tenant_with_active_lease_sees_tenant_documents(): void
    {
        $tenant = User::factory()->tenant()->create();
        Lease::factory()->create(['property_id' => $this->property->id, 'tenant_id' => $tenant->id]);

        $this->assertVisibleIds($tenant, [DocumentVisibility::LandlordTenant, DocumentVisibility::All]);
        $this->assertDownloadStatus($tenant, 'landlord_tenant', 200);
        $this->assertDownloadStatus($tenant, 'landlord_manager', 403);
        $this->assertDownloadStatus($tenant, 'landlord_only', 403);
    }

    public function test_tenant_without_active_lease_has_no_access(): void
    {
        $former = User::factory()->tenant()->create();
        Lease::factory()->ended()->create(['property_id' => $this->property->id, 'tenant_id' => $former->id]);
        $stranger = User::factory()->tenant()->create();

        foreach ([$former, $stranger] as $tenant) {
            $this->actingAs($tenant)
                ->getJson($this->apiUrl("/properties/{$this->property->id}/documents"))
                ->assertStatus(403);
            $this->assertDownloadStatus($tenant, 'all', 403);
        }
    }

    public function test_manager_who_rents_here_sees_tenant_documents(): void
    {
        $user = User::factory()->manager()->create();
        Property::factory()->create()->managers()->attach($user->id);
        Lease::factory()->create(['property_id' => $this->property->id, 'tenant_id' => $user->id]);

        $this->assertVisibleIds($user, [DocumentVisibility::LandlordTenant, DocumentVisibility::All]);
        $this->assertDownloadStatus($user, 'landlord_tenant', 200);
        $this->assertDownloadStatus($user, 'landlord_manager', 403);
    }

    public function test_upload_stores_file_privately_without_exposing_path(): void
    {
        $response = $this->actingAs($this->landlord)->postJson($this->apiUrl("/properties/{$this->property->id}/documents"), [
            'file' => UploadedFile::fake()->create('smlouva.pdf', 10, 'application/pdf'),
            'document_type' => 'contract',
            'name' => 'Smlouva',
        ])->assertStatus(201)
            ->assertJsonMissingPath('file_path')
            ->assertJsonPath('visibility', 'landlord_only')
            ->assertJsonPath('extension', 'pdf');

        $document = Document::query()->findOrFail($response->json('id'));
        $this->assertSame(route('api.documents.download', $document->id), $response->json('download_url'));
        Storage::disk(PrivateFile::DISK)->assertExists($document->file_path);
        Storage::disk('public')->assertMissing($document->file_path);
    }

    public function test_upload_rejects_dangerous_file_types(): void
    {
        $files = [
            UploadedFile::fake()->create('obrazek.svg', 1, 'image/svg+xml'),
            UploadedFile::fake()->create('stranka.html', 1, 'text/html'),
            UploadedFile::fake()->create('skript.php', 1, 'text/x-php'),
            UploadedFile::fake()->create('maskovany.pdf', 1, 'text/html'),
        ];

        foreach ($files as $file) {
            $this->actingAs($this->landlord)->postJson($this->apiUrl("/properties/{$this->property->id}/documents"), [
                'file' => $file,
                'document_type' => 'contract',
                'name' => 'Pokus',
            ])->assertStatus(422)->assertJsonValidationErrors('file');
        }

        $this->assertSame(4, Document::query()->count());
    }

    public function test_upload_accepts_real_legacy_word_and_excel_files(): void
    {
        // fixtury uložené z Wordu a Excelu jako „97–2003“: finfo je pozná jako msword / vnd.ms-excel
        foreach (['legacy.doc', 'legacy.xls'] as $fixture) {
            $this->actingAs($this->landlord)->postJson($this->apiUrl("/properties/{$this->property->id}/documents"), [
                'file' => new UploadedFile(base_path('tests/fixtures/'.$fixture), $fixture, null, null, true),
                'document_type' => 'other',
                'name' => 'Starý formát',
            ])->assertStatus(201)->assertJsonPath('extension', pathinfo($fixture, PATHINFO_EXTENSION));
        }
    }

    public function test_upload_rejects_unknown_visibility(): void
    {
        $this->actingAs($this->landlord)->postJson($this->apiUrl("/properties/{$this->property->id}/documents"), [
            'file' => UploadedFile::fake()->create('smlouva.pdf', 10, 'application/pdf'),
            'document_type' => 'contract',
            'name' => 'Smlouva',
            'visibility' => 'everyone',
        ])->assertStatus(422)->assertJsonValidationErrors('visibility');
    }

    public function test_only_owner_can_upload(): void
    {
        $manager = User::factory()->manager()->create();
        $this->property->managers()->attach($manager->id);

        foreach ([$manager, User::factory()->landlord()->create()] as $user) {
            $this->actingAs($user)->postJson($this->apiUrl("/properties/{$this->property->id}/documents"), [
                'file' => UploadedFile::fake()->create('smlouva.pdf', 10, 'application/pdf'),
                'document_type' => 'contract',
                'name' => 'Smlouva',
            ])->assertStatus(403);
        }
    }

    public function test_document_list_query_count_does_not_depend_on_document_count(): void
    {
        $tenant = User::factory()->tenant()->create();
        Lease::factory()->create(['property_id' => $this->property->id, 'tenant_id' => $tenant->id]);

        $few = $this->countListQueries($tenant);
        for ($i = 0; $i < 10; $i++) {
            $this->makeDocument($this->property, DocumentVisibility::All);
        }

        $this->assertSame($few, $this->countListQueries($tenant));
    }

    /**
     * @param  list<DocumentVisibility>  $visibilities
     */
    private function assertVisibleIds(User $user, array $visibilities): void
    {
        $expected = array_map(fn (DocumentVisibility $v) => $this->documents[$v->value]->id, $visibilities);
        sort($expected);

        $ids = $this->actingAs($user)
            ->getJson($this->apiUrl("/properties/{$this->property->id}/documents"))
            ->assertStatus(200)
            ->json('*.id');
        sort($ids);

        $this->assertSame($expected, $ids);
    }

    private function assertDownloadStatus(User $user, string $visibility, int $status): void
    {
        $this->actingAs($user)
            ->get($this->apiUrl("/documents/{$this->documents[$visibility]->id}/download"))
            ->assertStatus($status);
    }

    private function countListQueries(User $user): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->getJson($this->apiUrl("/properties/{$this->property->id}/documents"))->assertStatus(200);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function makeDocument(Property $property, DocumentVisibility $visibility): Document
    {
        $path = "documents/{$property->id}/".uniqid().'.pdf';
        Storage::disk(PrivateFile::DISK)->put($path, '%PDF-1.4');

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
