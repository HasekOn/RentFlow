<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\LeaseStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $property_id
 * @property int $tenant_id
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property numeric-string $rent_amount
 * @property numeric-string|null $deposit_amount
 * @property numeric-string|null $utility_advances
 * @property string|null $variable_symbol
 * @property string|null $contract_path
 * @property LeaseStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Property|null $property
 * @property-read User|null $tenant
 * @property-read Collection<Payment> $payments
 * @property-read Collection<Rating> $ratings
 */
class Lease extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Výchozí hodnoty stejné jako DB default v migraci (Model::shouldBeStrict).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => LeaseStatus::Active->value,
    ];

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

    /**
     * Kdo smlouvu vidí: vlastník nemovitosti, nájemník smlouvy a přiřazený správce, ten jen aktivní smlouvy
     * (stejně jako nájemníky v User::visibleTo). Smazané smlouvy řeší volající přes withTrashed().
     *
     * @param  Builder<Lease>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $visible) => $visible
            ->where('tenant_id', $user->id)
            ->orWhereHas('property', fn (Builder $property) => $property->where('landlord_id', $user->id))
            ->orWhere(fn (Builder $managed) => $managed
                ->where('status', LeaseStatus::Active)
                ->whereHas('property.managers', fn (Builder $manager) => $manager->whereKey($user->id))));
    }

    /**
     * Smlouvy, jejichž období se překrývá s daným (oba konce včetně, bez konce = na dobu neurčitou).
     *
     * @param  Builder<Lease>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $start, ?CarbonInterface $end): void
    {
        $query
            ->when($end !== null, fn (Builder $lease) => $lease->where('start_date', '<=', $end?->copy()->startOfDay()))
            ->where(fn (Builder $lease) => $lease
                ->whereNull('end_date')
                ->orWhere('end_date', '>=', $start->copy()->startOfDay()));
    }

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
            'status' => LeaseStatus::class,
            'start_date' => DateOnly::class,
            'end_date' => DateOnly::class,
            'rent_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'utility_advances' => 'decimal:2',
        ];
    }
}
