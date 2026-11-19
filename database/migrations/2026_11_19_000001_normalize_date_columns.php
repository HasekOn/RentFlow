<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Datová migrace (#27, #28): sloupce typu date mají v SQLite i čas (seeder a API ho tam uložily). Cast DateOnly
 * ukládá půlnoc, tady se srovnají staré hodnoty. V PHP po dávkách, bez SQL funkcí (DB-agnosticky). Schéma se nemění.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const array COLUMNS = [
        'payments' => ['due_date', 'paid_date'],
        'leases' => ['start_date', 'end_date'],
        'expenses' => ['expense_date'],
        'inventory_items' => ['purchase_date'],
        'meter_readings' => ['reading_date'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            DB::table($table)->select(['id', ...$columns])->orderBy('id')->chunkById(500, function ($rows) use ($table, $columns): void {
                foreach ($rows as $row) {
                    $changes = [];
                    foreach ($columns as $column) {
                        $value = $row->{$column};
                        if ($value === null) {
                            continue;
                        }
                        $midnight = Carbon::parse($value)->startOfDay()->format('Y-m-d H:i:s');
                        if ((string) $value !== $midnight) {
                            $changes[$column] = $midnight;
                        }
                    }
                    if ($changes !== []) {
                        DB::table($table)->where('id', $row->id)->update($changes);
                    }
                }
            });
        }
    }

    /**
     * Původní čas byl náhodný (seeder) nebo z requestu, vracet ho nejde a není proč.
     */
    public function down(): void {}
};
