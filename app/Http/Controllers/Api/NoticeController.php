<?php

namespace App\Http\Controllers\Api;

use App\Actions\Notices\DeleteNotice;
use App\Actions\Notices\SaveNotice;
use App\Http\Controllers\Api\Concerns\FindsProperty;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNoticeRequest;
use App\Http\Requests\UpdateNoticeRequest;
use App\Http\Resources\NoticeResource;
use App\Models\Notice;
use Illuminate\Http\JsonResponse;

class NoticeController extends Controller
{
    use FindsProperty;

    public function index(string $propertyId): JsonResponse
    {
        $property = $this->findProperty($propertyId);
        $this->authorize('view', $property);

        $notices = $property->notices()
            ->with('createdBy')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(NoticeResource::collection($notices));
    }

    public function store(StoreNoticeRequest $request, string $propertyId, SaveNotice $save): JsonResponse
    {
        $property = $this->findProperty($propertyId);
        $this->authorize('create', [Notice::class, $property]);

        $notice = $save->handle($property, $this->currentUser($request), $request->validated());

        $notice->load('createdBy');

        return response()->json(new NoticeResource($notice), 201);
    }

    public function update(UpdateNoticeRequest $request, string $id, SaveNotice $save): JsonResponse
    {
        $notice = $this->findNotice($id);
        $this->authorize('update', $notice);

        // u smazané nemovitosti vrátí policy 403 dřív, kontrola null jen zúží typ
        $property = $notice->property;
        if ($property === null) {
            abort(404);
        }
        $save->handle($property, $this->currentUser($request), $request->validated(), $notice);

        return response()->json(new NoticeResource($notice));
    }

    public function destroy(string $id, DeleteNotice $delete): JsonResponse
    {
        $notice = $this->findNotice($id);
        $this->authorize('delete', $notice);

        $delete->handle($notice);

        return response()->json([
            'message' => 'Notice deleted successfully.',
        ]);
    }

    private function findNotice(string $id): Notice
    {
        $notice = Notice::query()->with('property')->find($id);
        if ($notice === null) {
            abort(404);
        }

        return $notice;
    }
}
