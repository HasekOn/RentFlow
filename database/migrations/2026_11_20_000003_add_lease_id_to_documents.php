<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S6: dokument volitelně navázaný na smlouvu (smlouva, předávací protokol). Dokument pro nájemníka s vyplněnou
 * smlouvou vidí jen nájemník té smlouvy, ne další nájemník nemovitosti. Smazání smlouvy z DB vazbu jen vynuluje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('lease_id')->nullable()->after('property_id')->constrained()->nullOnDelete();
            $table->index('lease_id');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['lease_id']);
            $table->dropIndex(['lease_id']);
            $table->dropColumn('lease_id');
        });
    }
};
