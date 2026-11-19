<?php

namespace App\Models;

use App\Enums\PropertyImageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $property_id
 * @property string $image_path
 * @property PropertyImageType $type
 * @property int $uploaded_by
 * @property string|null $description
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Property|null $property
 */
class PropertyImage extends Model
{
    use HasFactory;

    /**
     * Výchozí hodnoty stejné jako DB default v migraci (Model::shouldBeStrict).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => PropertyImageType::Marketing->value,
        'sort_order' => 0,
    ];

    protected $fillable = [
        'property_id',
        'image_path',
        'type',
        'uploaded_by',
        'description',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PropertyImageType::class,
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
