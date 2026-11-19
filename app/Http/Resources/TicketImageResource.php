<?php

namespace App\Http\Resources;

use App\Models\TicketImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fotka tiketu: bez cesty k souboru (stahuje se autorizovanou routou), kdo ji nahrál jen jménem.
 *
 * @mixin TicketImage
 */
class TicketImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'image_url' => $this->image_url,
            'description' => $this->description,
            'created_at' => $this->created_at->toDateTimeString(),
            'uploader' => $this->whenLoaded('uploader', fn () => $this->uploader !== null ? [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
            ] : null),
        ];
    }
}
