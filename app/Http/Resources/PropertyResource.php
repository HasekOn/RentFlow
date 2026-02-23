<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'address' => $this->address,
            'city' => $this->city,
            'zip_code' => $this->zip_code,
            'size' => $this->size,
            'disposition' => $this->disposition,
            'floor' => $this->floor,
            'status' => $this->status,
            'purchase_price' => $this->purchase_price,
            'description' => $this->description,
            'created_at' => $this->created_at->toDateTimeString(),
            'landlord' => new UserResource($this->whenLoaded('landlord')),
            'leases' => LeaseResource::collection($this->whenLoaded('leases')),
            'meters' => MeterResource::collection($this->whenLoaded('meters')),
            'images' => PropertyImageResource::collection($this->whenLoaded('images')),
        ];
    }
}
