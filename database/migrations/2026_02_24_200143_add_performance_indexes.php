<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->index('status');
            $table->index('city');
            $table->index('landlord_id');
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->index('status');
            $table->index(['property_id', 'status']);
            $table->index('tenant_id');
            $table->index('end_date');
            $table->index('variable_symbol');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('status');
            $table->index('due_date');
            $table->index(['lease_id', 'status']);
            $table->index('variable_symbol');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->index('type');
            $table->index('expense_date');
            $table->index('property_id');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->index('status');
            $table->index('priority');
            $table->index('category');
            $table->index(['property_id', 'status']);
            $table->index('tenant_id');
            $table->index('assigned_to');
        });

        Schema::table('meter_readings', function (Blueprint $table) {
            $table->index(['meter_id', 'reading_date']);
        });

        Schema::table('ratings', function (Blueprint $table) {
            $table->index(['lease_id', 'rated_by']);
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['city']);
            $table->dropIndex(['landlord_id']);
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['property_id', 'status']);
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['end_date']);
            $table->dropIndex(['variable_symbol']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['due_date']);
            $table->dropIndex(['lease_id', 'status']);
            $table->dropIndex(['variable_symbol']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['expense_date']);
            $table->dropIndex(['property_id']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['priority']);
            $table->dropIndex(['category']);
            $table->dropIndex(['property_id', 'status']);
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['assigned_to']);
        });

        Schema::table('meter_readings', function (Blueprint $table) {
            $table->dropIndex(['meter_id', 'reading_date']);
        });

        Schema::table('ratings', function (Blueprint $table) {
            $table->dropIndex(['lease_id', 'rated_by']);
        });
    }
};
