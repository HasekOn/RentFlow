<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'rent_amount' => $this->rent_amount,
            'deposit_amount' => $this->deposit_amount,
            'utility_advances' => $this->utility_advances,
            'variable_symbol' => $this->variable_symbol,
            'contract_path' => $this->contract_path,
            'status' => $this->status,
            'created_at' => $this->created_at->toDateTimeString(),
            'property' => new PropertyResource($this->whenLoaded('property')),
            'tenant' => new UserResource($this->whenLoaded('tenant')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'ratings' => RatingResource::collection($this->whenLoaded('ratings')),
        ];
    }
}
