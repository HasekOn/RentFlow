<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaseStatus;
use App\Http\Controllers\Api\Concerns\FindsLease;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRatingRequest;
use App\Http\Resources\RatingResource;
use App\Models\Lease;
use App\Models\Rating;
use Illuminate\Http\JsonResponse;

class RatingController extends Controller
{
    use FindsLease;

    public function index(string $leaseId): JsonResponse
    {
        // hodnocení zůstávají i u smazané smlouvy (historie nájemníka)
        $lease = Lease::withTrashed()->find($leaseId);
        if ($lease === null) {
            abort(404);
        }
        $this->authorize('viewAny', [Rating::class, $lease]);

        $ratings = $lease->ratings()
            ->with('ratedBy')
            ->get();

        return response()->json(RatingResource::collection($ratings));
    }

    public function store(StoreRatingRequest $request, string $leaseId): JsonResponse
    {
        $lease = $this->findLease($leaseId);
        $this->authorize('create', [Rating::class, $lease]);

        // Only ended leases
        if ($lease->status === LeaseStatus::Active) {
            return response()->json([
                'message' => 'Cannot rate an active lease. End the lease first.',
            ], 422);
        }

        // Prevent duplicate rating for same category
        $exists = $lease->ratings()
            ->where('rated_by', $request->user()->id)
            ->where('category', $request->validated('category'))
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'You already rated this category for this lease.',
            ], 422);
        }

        $rating = Rating::query()->create([
            ...$request->validated(),
            'lease_id' => $lease->id,
            'rated_by' => $request->user()->id,
        ]);

        $rating->load('ratedBy');

        return response()->json(new RatingResource($rating), 201);
    }

    public function destroy(string $id): JsonResponse
    {
        $rating = Rating::query()->find($id);
        if ($rating === null) {
            abort(404);
        }
        // smlouvu i smazanou (index hodnocení je ukazuje), pro RatingPolicy::delete
        $rating->setRelation('lease', Lease::withTrashed()->with('property')->find($rating->lease_id));
        $this->authorize('delete', $rating);

        $rating->delete();

        return response()->json([
            'message' => 'Rating deleted successfully.',
        ]);
    }
}
