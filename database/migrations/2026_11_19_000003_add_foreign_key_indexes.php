<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #52: indexy na cizí klíče, podle kterých se filtruje a načítá (SQLite je k FK nevytváří sám). FK constrainty
 * už existují. Pivot property_manager má unikát (property_id, user_id), dotazy „co spravuje uživatel“ jdou přes
 * user_id. Duplicitní indexy (leases.variable_symbol, properties.landlord_id) se neřeší, dokud nevadí.
 */
return new class extends Migration
{
    /** @var array<string, string> tabulka => sloupec */
    private const array INDEXES = [
        'documents' => 'property_id',
        'meters' => 'property_id',
        'inventory_items' => 'property_id',
        'notices' => 'property_id',
        'property_images' => 'property_id',
        'ticket_comments' => 'ticket_id',
        'ticket_images' => 'ticket_id',
        'property_manager' => 'user_id',
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $column) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($column));
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $column) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex([$column]));
        }
    }
};
