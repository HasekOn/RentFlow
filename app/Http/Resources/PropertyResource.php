<?php

namespace App\Http\Resources;

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Property
 */
class PropertyResource extends JsonResource
{
    /** @return array<string, mixed> */
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
            // finance nemovitosti jen vlastníkovi (#20)
            'purchase_price' => $this->when($request->user()?->id === $this->landlord_id, $this->purchase_price),
            'description' => $this->description,
            'created_at' => $this->created_at->toDateTimeString(),
            'landlord' => UserResource::nested($this->whenLoaded('landlord'), $request),
            'leases' => LeaseResource::collection($this->whenLoaded('leases')),
            'meters' => MeterResource::collection($this->whenLoaded('meters')),
            'images' => PropertyImageResource::collection($this->whenLoaded('images')),
        ];
    }
}
