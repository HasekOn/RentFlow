<?php

namespace App\Http\Resources;

use App\Models\Rating;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Rating
 */
class RatingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'score' => $this->score,
            'comment' => $this->comment,
            'created_at' => $this->created_at->toDateTimeString(),
            'rated_by' => UserResource::nested($this->whenLoaded('ratedBy'), $request),
            'lease' => new LeaseResource($this->whenLoaded('lease')),
        ];
    }
}
