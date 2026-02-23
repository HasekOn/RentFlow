<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $property_id
 * @property int $tenant_id
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property float $rent_amount
 * @property float|null $deposit_amount
 * @property float|null $utility_advances
 * @property string|null $variable_symbol
 * @property string|null $contract_path
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Property|null $property
 * @property-read User|null $tenant
 * @property-read Collection<Payment> $payments
 * @property-read Collection<Rating> $ratings
 */
class Lease extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'tenant_id',
        'start_date',
        'end_date',
        'rent_amount',
        'deposit_amount',
        'utility_advances',
        'variable_symbol',
        'contract_path',
        'status',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'rent_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'utility_advances' => 'decimal:2',
        ];
    }
}
