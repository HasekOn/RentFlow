<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('rent_amount', 10, 2)->comment('Měsíční nájem');
            $table->decimal('deposit_amount', 10, 2)->nullable()->comment('Kauce');
            $table->decimal('utility_advances', 10, 2)->nullable()->comment('Zálohy na služby');
            $table->string('variable_symbol', 20)->nullable()->unique()->comment('VS pro párování plateb');
            $table->string('contract_path')->nullable()->comment('Cesta k PDF smlouvě');
            $table->enum('status', ['active', 'ended', 'terminated'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};
