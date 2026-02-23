<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => $this->amount,
            'expense_date' => $this->expense_date->format('Y-m-d'),
            'description' => $this->description,
            'invoice_path' => $this->invoice_path,
            'created_at' => $this->created_at->toDateTimeString(),
            'property' => new PropertyResource($this->whenLoaded('property')),
        ];
    }
}
