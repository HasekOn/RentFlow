<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #29: plocha a dispozice jsou volitelné (request je má nullable, NOT NULL v DB končil 500). Výjimka z pravidla
 * „migrace jen přidávající“ podle rozhodnutí z auditu: ->change() bez ztráty dat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('size', 6, 2)->nullable()->comment('Plocha v m²')->change();
            $table->string('disposition', 50)->nullable()->comment('1+1, 2+kk, atd.')->change();
        });
    }

    /**
     * Zpět jen když žádná nemovitost nemá prázdnou plochu ani dispozici (jinak by NOT NULL selhal).
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('size', 6, 2)->nullable(false)->comment('Plocha v m²')->change();
            $table->string('disposition', 50)->nullable(false)->comment('1+1, 2+kk, atd.')->change();
        });
    }
};
