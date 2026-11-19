<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Migrace kroků 2.12 a 2.14 nad daty: rollback a znovu migrate přes Migrator (bez obalující transakce testu,
 * proto DatabaseMigrations). `->change()` v SQLite přestaví tabulku properties a s FK kaskádami by
 * `drop table` smazal smlouvy, platby a další záznamy nemovitostí.
 */
class MigrationRollbackTest extends TestCase
{
    use DatabaseMigrations;

    public function test_rollback_and_migrate_keep_related_data(): void
    {
        $property = Property::factory()->create();
        $lease = Lease::factory()->create(['property_id' => $property->id]);
        $payment = Payment::factory()->create(['lease_id' => $lease->id]);

        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => 2, '--force' => true]));
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));

        $this->assertDatabaseHas('leases', ['id' => $lease->id, 'property_id' => $property->id]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->assertSame(1, (int) DB::scalar('PRAGMA foreign_keys'));
    }
}
