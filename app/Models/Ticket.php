<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Property|null $property
 * @property-read User|null $tenant
 * @property-read User|null $assignedUser
 * @property-read Collection<TicketComment> $comments
 */
class Ticket extends Model
{
    use HasFactory;

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

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }
}
