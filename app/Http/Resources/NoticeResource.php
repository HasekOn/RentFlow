<?php

namespace App\Http\Resources;

use App\Models\Notice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Notice
 */
class NoticeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->toDateTimeString(),
            'created_by' => UserResource::nested($this->whenLoaded('createdBy'), $request),
        ];
    }
}
