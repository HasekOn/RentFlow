<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'image_path' => $this->image_path,
            'image_url' => asset('storage/' . $this->image_path),
            'type' => $this->type,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at->toDateTimeString(),
            'uploaded_by' => new UserResource($this->whenLoaded('uploadedBy')),
        ];
    }
}
