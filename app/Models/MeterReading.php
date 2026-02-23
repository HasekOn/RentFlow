<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeterReading extends Model
{
    protected $fillable = [
        'meter_id',
        'reading_value',
        'reading_date',
        'submitted_by',
        'photo_proof',
    ];

    protected function casts(): array
    {
        return [
            'reading_value' => 'decimal:3',
            'reading_date' => 'date',
        ];
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
