<?php

namespace App\Models;

use App\Enums\InventoryCondition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $property_id
 * @property string $name
 * @property string|null $category
 * @property InventoryCondition $condition
 * @property Carbon|null $purchase_date
 * @property float|null $purchase_price
 * @property string|null $note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Property|null $property
 */
class InventoryItem extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Výchozí hodnoty stejné jako DB default v migraci (Model::shouldBeStrict).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'condition' => InventoryCondition::Good->value,
    ];

    protected $fillable = [
        'property_id',
        'name',
        'category',
        'condition',
        'purchase_date',
        'purchase_price',
        'note',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    protected function casts(): array
    {
        return [
            'condition' => InventoryCondition::class,
            'purchase_date' => 'date',
            'purchase_price' => 'decimal:2',
        ];
    }
}
