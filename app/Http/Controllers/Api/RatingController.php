<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Rating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function index(string $leaseId): JsonResponse
    {
        $lease = Lease::findOrFail($leaseId);

        $ratings = $lease->ratings()
            ->with('ratedBy')
            ->get();

        return response()->json($ratings);
    }

    public function store(Request $request, string $leaseId): JsonResponse
    {
        $lease = Lease::findOrFail($leaseId);

        // Only landlord can rate, and only ended leases
        if ($request->user()->role !== 'landlord') {
            return response()->json([
                'message' => 'Only landlords can rate tenants.',
            ], 403);
        }

        if ($lease->status === 'active') {
            return response()->json([
                'message' => 'Cannot rate an active lease. End the lease first.',
            ], 422);
        }

        $validated = $request->validate([
            'category' => ['required', 'in:apartment_condition,communication,rules,overall'],
            'score' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
        ]);

        // Prevent duplicate rating for same category
        $exists = $lease->ratings()
            ->where('rated_by', $request->user()->id)
            ->where('category', $validated['category'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'You already rated this category for this lease.',
            ], 422);
        }

        $validated['lease_id'] = $lease->id;
        $validated['rated_by'] = $request->user()->id;

        $rating = Rating::create($validated);
        $rating->load('ratedBy');

        return response()->json($rating, 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $rating = Rating::findOrFail($id);

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
