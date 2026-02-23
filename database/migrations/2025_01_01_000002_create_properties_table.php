<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade');
            $table->text('address');
            $table->string('city', 100)->nullable();
            $table->string('zip_code', 10)->nullable();
            $table->decimal('size', 6, 2)->comment('Plocha v m²');
            $table->string('disposition', 50)->comment('1+1, 2+kk, atd.');
            $table->integer('floor')->nullable();
            $table->enum('status', ['available', 'occupied', 'renovation'])->default('available');
            $table->decimal('purchase_price', 12, 2)->nullable()->comment('Pořizovací cena pro ROI');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
