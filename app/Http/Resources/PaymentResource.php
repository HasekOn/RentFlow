<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => $this->amount,
            'due_date' => $this->due_date->format('Y-m-d'),
            'paid_date' => $this->paid_date?->format('Y-m-d'),
            'variable_symbol' => $this->variable_symbol,
            'status' => $this->status,
            'note' => $this->note,
            'days_overdue' => $this->status !== 'paid' && $this->due_date->isPast()
                ? $this->due_date->diffInDays(now())
                : null,
            'created_at' => $this->created_at->toDateTimeString(),
            'lease' => new LeaseResource($this->whenLoaded('lease')),
        ];
    }
}
