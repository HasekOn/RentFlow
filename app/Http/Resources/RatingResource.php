<?php

namespace App\Http\Resources;

use App\Models\Lease;
use App\Models\Rating;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Komentář a hodnotitele vidí jen autor hodnocení, hodnocený nájemník a vlastník nemovitosti (jako S3 u profilu).
 * Ostatní (přiřazený správce, i u reaktivované smlouvy) jen kategorii a skóre.
 *
 * @mixin Rating
 */
class RatingResource extends JsonResource
{
    /**
     * @param  Rating  $resource
     * @param  Lease|null  $forLease  smlouva hodnocení, když není načtená jako relace (bez ní jen autor vidí detail)
     */
    public function __construct($resource, private readonly ?Lease $forLease = null)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->seesDetail($request)) {
            return [
                'id' => $this->id,
                'category' => $this->category,
                'score' => $this->score,
                'created_at' => $this->created_at->toDateTimeString(),
            ];
        }

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

    private function seesDetail(Request $request): bool
    {
        if ($this->rated_by === $request->user()?->getAuthIdentifier()) {
            return true;
        }
        $lease = $this->forLease ?? ($this->resource->relationLoaded('lease') ? $this->resource->lease : null);

        // nájemník smlouvy nebo vlastník nemovitosti, dávkově jako u kauce
        return $lease !== null && LeaseResource::showsFinance($lease, $request);
    }
}
