<?php

namespace App\Models;

use App\Enums\ExpenseType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $property_id
 * @property ExpenseType $type
 * @property float $amount
 * @property Carbon $expense_date
 * @property string|null $description
 * @property string|null $invoice_path
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Property|null $property
 */
class Expense extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Výchozí hodnoty stejné jako DB default v migraci (Model::shouldBeStrict).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => ExpenseType::Other->value,
    ];

    protected $fillable = [
        'property_id',
        'type',
        'amount',
        'expense_date',
        'description',
        'invoice_path',
    ];

    /**
     * Náklady jsou finance vlastníka: vidí je jen vlastník nemovitosti (ne správce ani nájemník, #16).
     *
     * @param  Builder<Expense>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereHas('property', fn (Builder $property) => $property->where('landlord_id', $user->id));
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    protected function casts(): array
    {
        return [
            'type' => ExpenseType::class,
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }
}
