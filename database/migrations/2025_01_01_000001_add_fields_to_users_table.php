<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['landlord', 'manager', 'tenant'])->default('tenant')->after('email');
            $table->decimal('trust_score', 5, 2)->default(50.00)->after('role');
            $table->string('phone', 20)->nullable()->after('trust_score');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'trust_score', 'phone']);
        });
    }
};
