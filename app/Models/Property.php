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
 * @property int $landlord_id
 * @property string $address
 * @property string|null $city
 * @property string|null $zip_code
 * @property float|null $size
 * @property string|null $disposition
 * @property int|null $floor
 * @property string $status
 * @property float|null $purchase_price
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $landlord
 * @property-read Collection<Lease> $leases
 * @property-read Collection<PropertyImage> $images
 * @property-read Collection<InventoryItem> $inventoryItems
 * @property-read Collection<Meter> $meters
 * @property-read Collection<Document> $documents
 * @property-read Collection<Ticket> $tickets
 * @property-read Collection<Expense> $expenses
 * @property-read Collection<Notice> $notices
 */
class Property extends Model
{
    use HasFactory;

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

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function notices(): HasMany
    {
        return $this->hasMany(Notice::class);
    }

    protected function casts(): array
    {
        return [
            'size' => 'decimal:2',
            'purchase_price' => 'decimal:2',
        ];
    }
}
