<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoticeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->toDateTimeString(),
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
        ];
    }
}
