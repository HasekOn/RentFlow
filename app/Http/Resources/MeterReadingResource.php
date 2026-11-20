<?php

namespace App\Http\Resources;

use App\Models\MeterReading;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MeterReading
 */
class MeterReadingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reading_value' => $this->reading_value,
            'reading_date' => $this->reading_date->format('Y-m-d'),
            'created_at' => $this->created_at->toDateTimeString(),
            'submitted_by' => UserResource::nested($this->whenLoaded('submittedBy'), $request),
        ];
    }
}
