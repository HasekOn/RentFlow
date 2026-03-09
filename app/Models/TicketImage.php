<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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
