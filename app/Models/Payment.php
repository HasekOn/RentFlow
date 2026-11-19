<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lease_id
 * @property PaymentType $type
 * @property numeric-string $amount
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

    /**
     * Kdo platbu vidí: vlastník nemovitosti, nájemník smlouvy a přiřazený správce u aktivních smluv (potvrzuje
     * platby ručně, appka není napojená na banku). Stejný výřez smluv jako Lease::visibleTo.
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereIn('lease_id', Lease::query()->visibleTo($user)->select('id'));
    }

    /**
     * Celé dny po splatnosti u nezaplacené platby, jinak null (splatná dnes ještě není po splatnosti).
     * Jeden výpočet pro API i UI.
     */
    public function daysOverdue(): ?int
    {
        if ($this->status === PaymentStatus::Paid || ! $this->due_date->lt(today())) {
            return null;
        }

        // Carbon 3 vrací float; mezi dvěma půlnocemi jde o celé dny, round jen pro jistotu kolem změny času
        return (int) round($this->due_date->copy()->startOfDay()->diffInDays(today()));
    }

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
            'due_date' => DateOnly::class,
            'paid_date' => DateOnly::class,
        ];
    }
}
