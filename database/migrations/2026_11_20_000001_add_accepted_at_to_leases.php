<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S1 + S2: kdy nájemník smlouvu přijal (pozvánka). Trust Score, hodnocení, platby a kontakty nájemníka jen u přijatých
 * smluv. Existující smlouvy vznikly před pozvánkami, berou se jako přijaté v okamžiku založení.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('status');
        });

        DB::table('leases')->whereNotNull('created_at')->update(['accepted_at' => DB::raw('created_at')]);
        DB::table('leases')->whereNull('created_at')->update(['accepted_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn('accepted_at');
        });
    }
};
