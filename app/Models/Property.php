<?php

namespace App\Models;

use App\Enums\PropertyStatus;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $landlord_id
 * @property string $address
 * @property string|null $city
 * @property string|null $zip_code
 * @property float|null $size
 * @property string|null $disposition
 * @property int|null $floor
 * @property PropertyStatus $status
 * @property numeric-string|null $purchase_price
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $landlord
 * @property-read Collection<int, Lease> $leases
 * @property-read Collection<int, PropertyImage> $images
 * @property-read Collection<int, InventoryItem> $inventoryItems
 * @property-read Collection<int, Meter> $meters
 * @property-read Collection<int, Document> $documents
 * @property-read Collection<int, Ticket> $tickets
 * @property-read Collection<int, Expense> $expenses
 * @property-read Collection<int, Notice> $notices
 */
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Výchozí hodnoty stejné jako DB default v migraci (Model::shouldBeStrict).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => PropertyStatus::Available->value,
    ];

    protected $fillable = [
        'landlord_id',
        'address',
        'city',
        'zip_code',
        'size',
        'disposition',
        'floor',
        'status',
        'purchase_price',
        'description',
    ];

    /**
     * Kdo nemovitost vidí: vlastník, přiřazený správce a nájemník, který tu teď bydlí (Lease::current: aktivní
     * smlouva od `start_date`). Podle vztahu, ne role. Na tomhle stojí i oznámení, měřiče, inventář a tikety.
     *
     * @param  Builder<Property>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $visible) => $visible
            ->where('landlord_id', $user->id)
            ->orWhereHas('managers', fn (Builder $manager) => $manager->whereKey($user->id))
            ->orWhereHas('leases', fn (Builder $lease) => $lease
                ->where('tenant_id', $user->id)
                ->current()));
    }

    /** @return BelongsTo<User, $this> */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /** @return HasMany<Lease, $this> */
    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    /** @return HasMany<PropertyImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class);
    }

    /** @return HasMany<InventoryItem, $this> */
    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    /** @return HasMany<Meter, $this> */
    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** @return HasMany<Notice, $this> */
    public function notices(): HasMany
    {
        return $this->hasMany(Notice::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'property_manager');
    }

    protected function casts(): array
    {
        return [
            'status' => PropertyStatus::class,
            'size' => 'decimal:2',
            'purchase_price' => 'decimal:2',
        ];
    }
}
