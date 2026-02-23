<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRatingRequest;
use App\Http\Resources\RatingResource;
use App\Models\Lease;
use App\Models\Rating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function index(string $leaseId): JsonResponse
    {
        $lease = Lease::query()->findOrFail($leaseId);

        $ratings = $lease->ratings()
            ->with('ratedBy')
            ->get();

        return response()->json(RatingResource::collection($ratings));
    }

    public function store(StoreRatingRequest $request, string $leaseId): JsonResponse
    {
        $lease = Lease::query()->findOrFail($leaseId);

        // Only landlord can rate
        if ($request->user()->role !== 'landlord') {
            return response()->json([
                'message' => 'Only landlords can rate tenants.',
            ], 403);
        }

        // Only ended leases
        if ($lease->status === 'active') {
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

    public function destroy(Request $request, string $id): JsonResponse
    {
        $rating = Rating::query()->findOrFail($id);

        if ($rating->rated_by !== $request->user()->id) {
            return response()->json([
                'message' => 'You can only delete your own ratings.',
            ], 403);
        }

        $rating->delete();

        return response()->json([
            'message' => 'Rating deleted successfully.',
        ]);
    }
}
