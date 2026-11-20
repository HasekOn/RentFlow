<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Rating;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Migrace od kroku 2.12 nad daty: rollback a znovu migrate přes Migrator (bez obalující transakce testu,
 * proto DatabaseMigrations). `->change()` v SQLite přestaví tabulky properties a leases (stavy smlouvy, S1)
 * a s FK kaskádami by `drop table` smazal smlouvy, platby, hodnocení a další záznamy.
 */
class MigrationRollbackTest extends TestCase
{
    use DatabaseMigrations;

    public function test_rollback_and_migrate_keep_related_data(): void
    {
        $property = Property::factory()->create();
        $lease = Lease::factory()->create(['property_id' => $property->id]);
        $payment = Payment::factory()->create(['lease_id' => $lease->id]);
        $rating = Rating::factory()->create(['lease_id' => $lease->id]);
        $documentId = DB::table('documents')->insertGetId([
            'property_id' => $property->id, 'document_type' => 'Smlouva', 'name' => 'Smlouva', 'file_path' => 'documents/x.pdf',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // 2026_11_19_* (3) a 2026_11_20_* (4)
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => 7, '--force' => true]));
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));

        $this->assertDatabaseHas('leases', ['id' => $lease->id, 'property_id' => $property->id, 'status' => 'active']);
        $this->assertNotNull(DB::table('leases')->where('id', $lease->id)->value('accepted_at'));
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
        $this->assertDatabaseHas('ratings', ['id' => $rating->id]);
        $this->assertDatabaseHas('documents', ['id' => $documentId, 'lease_id' => null]);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->assertSame(1, (int) DB::scalar('PRAGMA foreign_keys'));
    }
}
