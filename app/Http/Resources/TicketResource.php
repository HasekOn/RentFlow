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
            'tenant' => new UserResource($this->whenLoaded('tenant')),
            'assigned_user' => new UserResource($this->whenLoaded('assignedUser')),
            'comments' => TicketCommentResource::collection($this->whenLoaded('comments')),
        ];
    }
}
