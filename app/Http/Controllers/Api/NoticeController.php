<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoticeController extends Controller
{
    public function index(string $propertyId): JsonResponse
    {
        $property = Property::findOrFail($propertyId);

        $notices = $property->notices()
            ->with('createdBy')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notices);
    }

    public function store(Request $request, string $propertyId): JsonResponse
    {
        $property = Property::findOrFail($propertyId);

        if ($property->landlord_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You can only create notices for your own properties.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        $validated['property_id'] = $property->id;
        $validated['created_by'] = $request->user()->id;

        $notice = Notice::create($validated);
        $notice->load('createdBy');

        return response()->json($notice, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $notice = Notice::findOrFail($id);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $notice->update($validated);

        return response()->json($notice);
    }

    public function destroy(string $id): JsonResponse
    {
        $notice = Notice::findOrFail($id);
        $notice->delete();

        return response()->json([
            'message' => 'Notice deleted successfully.',
        ]);
    }
}
