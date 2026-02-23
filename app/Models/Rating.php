<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Lease|null $lease
 * @property-read User|null $ratedBy
 */
class Rating extends Model
{
    use HasFactory;

    protected $fillable = [
        'lease_id',
        'rated_by',
        'category',
        'score',
        'comment',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function ratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_by');
    }
}
