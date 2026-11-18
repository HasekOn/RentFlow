<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lease_id
 * @property PaymentType $type
 * @property float $amount
 * @property Carbon $due_date
 * @property Carbon|null $paid_date
 * @property string|null $variable_symbol
 * @property PaymentStatus $status
 * @property string|null $note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Lease|null $lease
 */
class Payment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Výchozí hodnoty stejné jako DB default v migraci (Model::shouldBeStrict).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => PaymentType::Rent->value,
        'status' => PaymentStatus::Unpaid->value,
    ];

    protected $fillable = [
        'lease_id',
        'type',
        'amount',
        'due_date',
        'paid_date',
        'variable_symbol',
        'status',
        'note',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    protected function casts(): array
    {
        return [
            'type' => PaymentType::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_date' => 'date',
        ];
    }
}
