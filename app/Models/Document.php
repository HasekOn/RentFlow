<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $property_id
 * @property string $document_type
 * @property string $name
 * @property string $file_path
 * @property string $visibility
 * @property int $uploaded_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Property|null $property
 * @property-read User|null $uploadedBy
 */
class Document extends Model
{
    use SoftDeletes;

    /**
     * Visibility options:
     * - landlord_only: Only the landlord
     * - landlord_tenant: Landlord + tenant with active lease
     * - landlord_manager: Landlord + assigned managers
     * - all: Everyone with access to the property
     */
    public const array VISIBILITY_OPTIONS = [
        'landlord_only',
        'landlord_tenant',
        'landlord_manager',
        'all',
    ];

    protected $fillable = [
        'property_id',
        'document_type',
        'name',
        'file_path',
        'visibility',
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
