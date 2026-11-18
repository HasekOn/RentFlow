<?php

namespace App\Models;

use App\Enums\DocumentVisibility;
use App\Enums\LeaseStatus;
use Illuminate\Database\Eloquent\Builder;
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
 * @property DocumentVisibility $visibility
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
     * Výchozí hodnoty stejné jako DB default v migraci (Model::shouldBeStrict).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'visibility' => DocumentVisibility::LandlordOnly->value,
    ];

    public const int MAX_FILE_KB = 10240;

    /** Whitelist typů dokumentů (žádné HTML ani SVG). */
    public const string MIMES = 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp';

    protected $fillable = [
        'property_id',
        'document_type',
        'name',
        'file_path',
        'visibility',
        'uploaded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => DocumentVisibility::class,
        ];
    }

    /**
     * Dokumenty, které uživatel smí vidět. Rozhoduje vztah k nemovitosti, ne globální role:
     * vlastník vidí vše, přiřazený správce landlord_manager + all, nájemník s aktivní smlouvou landlord_tenant + all.
     *
     * @param  Builder<Document>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user): void {
            $query->whereHas('property', fn (Builder $property) => $property->where('landlord_id', $user->id))
                ->orWhere(fn (Builder $query) => $query
                    ->whereIn('visibility', [DocumentVisibility::LandlordManager, DocumentVisibility::All])
                    ->whereHas('property.managers', fn (Builder $manager) => $manager->whereKey($user->id)))
                ->orWhere(fn (Builder $query) => $query
                    ->whereIn('visibility', [DocumentVisibility::LandlordTenant, DocumentVisibility::All])
                    ->whereHas('property.leases', fn (Builder $lease) => $lease
                        ->where('tenant_id', $user->id)
                        ->where('status', LeaseStatus::Active)));
        });
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
