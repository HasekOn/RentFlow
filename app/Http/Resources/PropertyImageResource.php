<?php

namespace App\Http\Resources;

use App\Models\PropertyImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PropertyImage
 */
class PropertyImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'image_url' => route('api.property-images.file', $this->id),
            'type' => $this->type,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at->toDateTimeString(),
            'uploaded_by' => new UserResource($this->whenLoaded('uploadedBy')),
        ];
    }
}
