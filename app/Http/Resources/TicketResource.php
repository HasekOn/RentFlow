<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'status' => $this->status,
            'priority' => $this->priority,
            'resolved_at' => $this->resolved_at?->toDateTimeString(),
            'resolution_time' => $this->resolved_at
                ? $this->created_at->diffInHours($this->resolved_at).'h'
                : null,
            'created_at' => $this->created_at->toDateTimeString(),
            'property' => new PropertyResource($this->whenLoaded('property')),
            'tenant' => UserResource::nested($this->whenLoaded('tenant'), $request),
            'assigned_user' => UserResource::nested($this->whenLoaded('assignedUser'), $request),
            'comments' => TicketCommentResource::collection($this->whenLoaded('comments')),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($img) => [
                'id' => $img->id,
                'image_url' => $img->image_url,
                'description' => $img->description,
                'created_at' => $img->created_at->toDateTimeString(),
                'uploader' => $img->uploader ? [
                    'id' => $img->uploader->id,
                    'name' => $img->uploader->name,
                ] : null,
            ])),
        ];
    }
}
