<?php

namespace App\Actions\Ratings;

use App\Enums\LeaseStatus;
use App\Models\Lease;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hodnocení nájemníka po skončení smlouvy a přepočet jeho trust score (#36, README to slibuje).
 * Autorizaci dělá volající.
 */
final class CreateRating
{
    /**
     * @param  array<string, mixed>  $data  validovaná pole (category, score, comment)
     */
    public function handle(Lease $lease, User $author, array $data): Rating
    {
        return DB::transaction(function () use ($lease, $author, $data): Rating {
            // hodnotit jde jen smlouvu, kterou nájemník přijal (S2), a až po jejím konci
            if (! $lease->isAccepted()) {
                throw ValidationException::withMessages([
                    'lease' => __('Only a lease the tenant has accepted can be rated.'),
                ]);
            }
            if ($lease->status === LeaseStatus::Active) {
                throw ValidationException::withMessages([
                    'lease' => __('Cannot rate an active lease. End the lease first.'),
                ]);
            }

            $duplicate = $lease->ratings()
                ->where('rated_by', $author->id)
                ->where('category', $data['category'])
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'category' => __('You already rated this category for this lease.'),
                ]);
            }

            $rating = Rating::query()->create([...$data, 'lease_id' => $lease->id, 'rated_by' => $author->id]);
            $lease->tenant?->recalculateTrustScore();

            return $rating;
        });
    }
}
