<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Meter|null $meter
 * @property-read User|null $submittedBy
 */
class MeterReading extends Model
{
    use HasFactory;

    protected $fillable = [
        'meter_id',
        'reading_value',
        'reading_date',
        'submitted_by',
        'photo_proof',
    ];

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    protected function casts(): array
    {
        return [
            'reading_value' => 'decimal:3',
            'reading_date' => 'date',
        ];
    }
}
