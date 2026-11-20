<?php

namespace App\Models;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $property_id
 * @property int $tenant_id
 * @property string $title
 * @property string $description
 * @property TicketCategory $category
 * @property TicketStatus $status
 * @property TicketPriority $priority
 * @property int|null $assigned_to
 * @property Carbon|null $resolved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Property|null $property
 * @property-read User|null $tenant
 * @property-read User|null $assignedUser
 * @property-read Collection<int, TicketComment> $comments
 * @property-read Collection<int, TicketImage> $images
 */
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Výchozí hodnoty stejné jako DB default v migraci (Model::shouldBeStrict).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'category' => TicketCategory::Other->value,
        'status' => TicketStatus::New->value,
        'priority' => TicketPriority::Medium->value,
    ];

    protected $fillable = [
        'property_id',
        'tenant_id',
        'title',
        'description',
        'category',
        'status',
        'priority',
        'assigned_to',
        'resolved_at',
    ];

    /**
     * Kdo tiket vidí: autor (i po skončení smlouvy) a každý, kdo vidí nemovitost (Property::visibleTo: vlastník,
     * přiřazený správce, nájemník s aktivní smlouvou). Podle vztahu, ne podle role, takže správce, který je jinde
     * nájemníkem, vidí tikety svého bytu i spravovaných nemovitostí (#24).
     *
     * @param  Builder<Ticket>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $visible) => $visible
            ->where('tenant_id', $user->id)
            ->orWhereIn('property_id', Property::query()->visibleTo($user)->select('id')));
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<User, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<TicketComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /**
     * @return HasMany<TicketImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(TicketImage::class);
    }

    protected function casts(): array
    {
        return [
            'category' => TicketCategory::class,
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'resolved_at' => 'datetime',
        ];
    }
}
