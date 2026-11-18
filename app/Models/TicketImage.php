<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $uploaded_by
 * @property string $image_path
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string $image_url
 * @property-read Ticket|null $ticket
 * @property-read User|null $uploader
 */
class TicketImage extends Model
{
    protected $fillable = [
        'ticket_id',
        'uploaded_by',
        'image_path',
        'description',
    ];

    protected $appends = ['image_url'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getImageUrlAttribute(): string
    {
        return url(Storage::url($this->image_path));
    }
}
