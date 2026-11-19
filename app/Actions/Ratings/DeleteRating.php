<?php

namespace App\Actions\Ratings;

use App\Models\Rating;
use Illuminate\Support\Facades\DB;

/**
 * Smazání hodnocení a přepočet trust score hodnoceného nájemníka (#36). Autorizaci dělá volající.
 */
final class DeleteRating
{
    public function handle(Rating $rating): void
    {
        DB::transaction(function () use ($rating): void {
            $rating->delete();
            $rating->lease?->tenant?->recalculateTrustScore();
        });
    }
}
