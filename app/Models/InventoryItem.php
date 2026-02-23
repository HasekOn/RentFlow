<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $property_id
 * @property string $name
 * @property string|null $category
 * @property string $condition
 * @property Carbon|null $purchase_date
 * @property float|null $purchase_price
 * @property string|null $note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Property|null $property
 */
class InventoryItem extends Model
{
    use HasFactory;

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
            'purchase_date' => 'date',
            'purchase_price' => 'decimal:2',
        ];
    }
}
