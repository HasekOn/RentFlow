<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNoticeRequest;
use App\Http\Requests\UpdateNoticeRequest;
use App\Models\Notice;
use App\Models\Property;
use Illuminate\Http\JsonResponse;

class NoticeController extends Controller
{
    public function index(string $propertyId): JsonResponse
    {
        $this->authorize('viewAny', Notice::class);

        $property = Property::query()->findOrFail($propertyId);

        $notices = $property->notices()
            ->with('createdBy')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notices);
    }

    public function store(StoreNoticeRequest $request, string $propertyId): JsonResponse
    {
        $this->authorize('create', Notice::class);

        $property = Property::query()->findOrFail($propertyId);

        if ($property->landlord_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You can only create notices for your own properties.',
            ], 403);
        }

        $notice = Notice::query()->create([
            ...$request->validated(),
            'property_id' => $property->id,
            'created_by' => $request->user()->id,
        ]);

        $notice->load('createdBy');

        return response()->json($notice, 201);
    }

    public function update(UpdateNoticeRequest $request, string $id): JsonResponse
    {
        $notice = Notice::query()->findOrFail($id);

        $this->authorize('update', $notice);

        $notice->update($request->validated());

        return response()->json($notice);
    }

    public function destroy(string $id): JsonResponse
    {
        $notice = Notice::query()->findOrFail($id);

        $this->authorize('delete', $notice);

        $notice->delete();

        return response()->json([
            'message' => 'Notice deleted successfully.',
        ]);
    }
}
