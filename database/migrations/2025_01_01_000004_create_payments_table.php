<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['rent', 'utilities', 'deposit', 'other'])->default('rent');
            $table->decimal('amount', 10, 2);
            $table->date('due_date')->comment('Datum splatnosti');
            $table->date('paid_date')->nullable()->comment('Datum zaplacení');
            $table->string('variable_symbol', 20)->nullable();
            $table->enum('status', ['paid', 'unpaid', 'overdue'])->default('unpaid');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
