<?php

namespace App\Http\Controllers\Api;

use App\Actions\Ratings\CreateRating;
use App\Actions\Ratings\DeleteRating;
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

    public function store(StoreRatingRequest $request, string $leaseId, CreateRating $create): JsonResponse
    {
        $lease = $this->findLease($leaseId);
        $this->authorize('create', [Rating::class, $lease]);

        $rating = $create->handle($lease, $request->user(), $request->validated());

        $rating->load('ratedBy');

        return response()->json(new RatingResource($rating), 201);
    }

    public function destroy(string $id, DeleteRating $delete): JsonResponse
    {
        $rating = Rating::query()->find($id);
        if ($rating === null) {
            abort(404);
        }
        // smlouvu i smazanou (index hodnocení je ukazuje), pro RatingPolicy::delete
        $rating->setRelation('lease', Lease::withTrashed()->with('property', 'tenant')->find($rating->lease_id));
        $this->authorize('delete', $rating);

        $delete->handle($rating);

        return response()->json([
            'message' => 'Rating deleted successfully.',
        ]);
    }
}
