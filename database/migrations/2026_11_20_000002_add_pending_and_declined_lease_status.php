<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S1 + S2: stav pozvánky (`pending`) a odmítnuté smlouvy (`declined`), nová smlouva je výchozí `pending`.
 * Výjimka z pravidla „migrace jen přidávající“ (jako #29): ->change() rozšíří výčet bez ztráty dat. SQLite tabulku
 * přestaví s vypnutými FK (migrace v SQLite neběží v transakci), hlídá MigrationRollbackTest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->enum('status', ['pending', 'active', 'ended', 'terminated', 'declined'])->default('pending')->change();
        });
    }

    /**
     * Zpět: nepřijaté pozvánky a odmítnuté smlouvy jako ukončené, původní výčet s výchozím `active`.
     */
    public function down(): void
    {
        DB::table('leases')->whereIn('status', ['pending', 'declined'])->update(['status' => 'terminated']);

        Schema::table('leases', function (Blueprint $table) {
            $table->enum('status', ['active', 'ended', 'terminated'])->default('active')->change();
        });
    }
};
