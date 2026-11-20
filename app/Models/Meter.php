<?php

namespace App\Models;

use App\Enums\MeterType;
use Database\Factories\MeterFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $property_id
 * @property MeterType $meter_type
 * @property string|null $serial_number
 * @property string|null $location
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Property|null $property
 * @property-read Collection<int, MeterReading> $readings
 */
class Meter extends Model
{
    /** @use HasFactory<MeterFactory> */
    use HasFactory;

    /**
     * Výchozí hodnoty stejné jako DB default v migraci (Model::shouldBeStrict).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'meter_type' => MeterType::Electricity->value,
    ];

    protected $fillable = [
        'property_id',
        'meter_type',
        'serial_number',
        'location',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meter_type' => MeterType::class,
        ];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return HasMany<MeterReading, $this>
     */
    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }
}
