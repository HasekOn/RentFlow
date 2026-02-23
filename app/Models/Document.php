<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Property|null $property
 * @property-read User|null $uploadedBy
 */
class Document extends Model
{
    protected $fillable = [
        'property_id',
        'document_type',
        'name',
        'file_path',
        'uploaded_by',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
