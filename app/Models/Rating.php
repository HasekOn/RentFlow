<?php

namespace App\Models;

use App\Enums\RatingCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lease_id
 * @property int $rated_by
 * @property RatingCategory $category
 * @property int $score
 * @property string|null $comment
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Lease|null $lease
 * @property-read User|null $ratedBy
 */
class Rating extends Model
{
    use HasFactory;

    /**
     * Výchozí hodnoty stejné jako DB default v migraci (Model::shouldBeStrict).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'category' => RatingCategory::Overall->value,
    ];

    protected $fillable = [
        'lease_id',
        'rated_by',
        'category',
        'score',
        'comment',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => RatingCategory::class,
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function ratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_by');
    }
}
