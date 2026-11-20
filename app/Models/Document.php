<?php

namespace App\Models;

use App\Enums\DocumentVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $property_id
 * @property int|null $lease_id volitelně smlouva (S6): dokument pro nájemníka pak vidí jen nájemník té smlouvy
 * @property string $document_type
 * @property string $name
 * @property string $file_path
 * @property DocumentVisibility $visibility
 * @property int $uploaded_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Property|null $property
 * @property-read Lease|null $lease
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
        'lease_id',
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
     * vlastník vidí vše, přiřazený správce landlord_manager + all, nájemník, který tu teď bydlí (Lease::current),
     * landlord_tenant + all.
     * Dokument landlord_tenant navázaný na smlouvu vidí jen nájemník té smlouvy, ne další nájemník (S6).
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
                        ->current())
                    ->where(fn (Builder $ownLease) => $ownLease
                        ->where('visibility', DocumentVisibility::All)
                        ->orWhereNull('lease_id')
                        ->orWhereIn('lease_id', Lease::withTrashed()->where('tenant_id', $user->id)->select('id'))));
        });
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<Lease, $this> */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
