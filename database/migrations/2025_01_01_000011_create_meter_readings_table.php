<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meter_id')->constrained()->onDelete('cascade');
            $table->decimal('reading_value', 12, 3);
            $table->date('reading_date');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('photo_proof')->nullable()->comment('Fotka odečtu');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meter_readings');
    }
};
