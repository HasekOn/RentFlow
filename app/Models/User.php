<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\TrustScoreService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $role
 * @property float $trust_score
 * @property string|null $phone
 * @property Carbon|null $email_verified_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<Property> $ownedProperties
 * @property-read Collection<Lease> $leases
 * @property-read Collection<Ticket> $tickets
 * @property-read Collection<Ticket> $assignedTickets
 * @property-read Collection<Rating> $givenRatings
 * @property-read DatabaseNotificationCollection $notifications
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'trust_score',
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

    public function ownedProperties(): HasMany
    {
        return $this->hasMany(Property::class, 'landlord_id');
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class, 'tenant_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'tenant_id');
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function givenRatings(): HasMany
    {
        return $this->hasMany(Rating::class, 'rated_by');
    }

    /**
     * Recalculate trust score using TrustScoreService
     */
    public function recalculateTrustScore(): float
    {
        $service = new TrustScoreService;

        return $service->calculate($this);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'trust_score' => 'decimal:2',
        ];
    }
}
