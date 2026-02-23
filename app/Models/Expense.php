<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $property_id
 * @property string $type
 * @property float $amount
 * @property Carbon $expense_date
 * @property string|null $description
 * @property string|null $invoice_path
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Property|null $property
 */
class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'type',
        'amount',
        'expense_date',
        'description',
        'invoice_path',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }
}
