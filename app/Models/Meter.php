<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Property|null $property
 * @property-read Collection<MeterReading> $readings
 */
class Meter extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'meter_type',
        'serial_number',
        'location',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }
}
