<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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

    /** Cesta k souboru se ven neposílá, soubor se stahuje autorizovanou routou. */
    protected $hidden = ['image_path'];

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
        return route('api.ticket-images.file', [$this->ticket_id, $this->id]);
    }
}
