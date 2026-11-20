<?php

namespace App\Models;

use App\Enums\LeaseStatus;
use App\Enums\Role;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\TrustScoreService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Role $role
 * @property float $trust_score
 * @property string|null $phone
 * @property Carbon|null $email_verified_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, Property> $ownedProperties
 * @property-read Collection<int, Lease> $leases
 * @property-read Collection<int, Ticket> $tickets
 * @property-read Collection<int, Ticket> $assignedTickets
 * @property-read Collection<int, Rating> $givenRatings
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Výchozí hodnoty stejné jako DB default v migraci (Model::shouldBeStrict).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => Role::Tenant->value,
        'trust_score' => '50.00',
    ];

    /**
     * Role a trust_score záměrně chybí: nastavují se jen explicitně (registrace, správci, TrustScoreService).
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function isLandlord(): bool
    {
        return $this->role === Role::Landlord;
    }

    public function isManager(): bool
    {
        return $this->role === Role::Manager;
    }

    public function isTenant(): bool
    {
        return $this->role === Role::Tenant;
    }

    /** @return HasMany<Property, $this> */
    public function ownedProperties(): HasMany
    {
        return $this->hasMany(Property::class, 'landlord_id');
    }

    /** @return HasMany<Lease, $this> */
    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class, 'tenant_id');
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'tenant_id');
    }

    /** @return HasMany<Ticket, $this> */
    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    /** @return HasMany<Rating, $this> */
    public function givenRatings(): HasMany
    {
        return $this->hasMany(Rating::class, 'rated_by');
    }

    /** @return BelongsToMany<Property, $this> */
    public function managedProperties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_manager');
    }

    /**
     * Koho uživatel vidí (Lidé, detail osoby, trust score, hodnocení). Podle vztahu k nemovitostem, ne podle role:
     * sebe, nájemníky smluv na svých nemovitostech (i ukončených) a jejich správce; jako správce nájemníky aktivních
     * smluv a spolusprávce přiřazených nemovitostí.
     *
     * @param  Builder<User>  $query
     */
    public function scopeVisibleTo(Builder $query, User $viewer): void
    {
        $managedByViewer = fn (Builder $property) => $property->whereHas(
            'managers',
            fn (Builder $manager) => $manager->whereKey($viewer->id),
        );

        $query->where(fn (Builder $visible) => $visible
            ->whereKey($viewer->id)
            // nájemník jen přes smlouvu, kterou přijal (S1): pozvánka sama kontakty ani skóre neprozradí
            ->orWhereHas('leases', fn (Builder $lease) => $lease
                ->accepted()
                ->whereHas('property', fn (Builder $property) => $property->where('landlord_id', $viewer->id)))
            ->orWhereHas('managedProperties', fn (Builder $property) => $property->where('landlord_id', $viewer->id))
            ->orWhereHas('leases', fn (Builder $lease) => $lease
                ->where('status', LeaseStatus::Active)
                ->whereHas('property', $managedByViewer))
            ->orWhereHas('managedProperties', $managedByViewer));
    }

    /**
     * Recalculate trust score using TrustScoreService
     */
    public function recalculateTrustScore(): float
    {
        return app(TrustScoreService::class)->calculate($this);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'trust_score' => 'decimal:2',
        ];
    }
}
