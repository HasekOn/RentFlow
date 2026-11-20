<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S10: variabilní symbol je unikátní jen v rámci pronajímatele (validace ve Store/UpdateLeaseRequest), ne napříč
 * všemi. Výjimka z pravidla „migrace jen přidávající“: zruší se jen unikátní index, obyčejný index
 * `leases_variable_symbol_index` z add_performance_indexes zůstává pro párování plateb.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropUnique(['variable_symbol']);
        });
    }

    /**
     * Zpět jen když dva pronajímatelé nemají stejný VS (jinak unikátní index selže).
     */
    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->unique('variable_symbol');
        });
    }
};
