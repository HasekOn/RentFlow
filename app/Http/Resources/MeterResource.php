<?php

namespace App\Http\Resources;

use App\Models\Meter;
use App\Models\MeterReading;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Meter
 */
class MeterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meter_type' => $this->meter_type,
            'serial_number' => $this->serial_number,
            'location' => $this->location,
            'latest_reading' => $this->whenLoaded('readings', function () {
                /** @var MeterReading|null $latest */
                $latest = $this->readings->sortByDesc('reading_date')->first();
                return $latest ? [
                    'value' => $latest->reading_value,
                    'date' => $latest->reading_date->format('Y-m-d'),
                ] : null;
            }),
            'created_at' => $this->created_at->toDateTimeString(),
            'property' => new PropertyResource($this->whenLoaded('property')),
            'readings' => MeterReadingResource::collection($this->whenLoaded('readings')),
        ];
    }
}
