<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePropertyImageRequest;
use App\Http\Requests\UpdatePropertyImageRequest;
use App\Models\Property;
use App\Models\PropertyImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PropertyImageController extends Controller
{
    public function index(string $propertyId): JsonResponse
    {
        $property = Property::query()->findOrFail($propertyId);

        $images = $property->images()
            ->orderBy('sort_order')
            ->get();

        return response()->json($images);
    }

    public function store(StorePropertyImageRequest $request, string $propertyId): JsonResponse
    {
        $property = Property::query()->findOrFail($propertyId);

        // Store file in storage/app/public/properties/{id}/
        $path = $request->file('image')->store(
            'properties/' . $property->id,
            'public'
        );

        $image = PropertyImage::query()->create([
            'property_id' => $property->id,
            'image_path' => $path,
            'type' => $request->validated('type', 'marketing'),
            'uploaded_by' => $request->user()->id,
            'description' => $request->validated('description'),
            'sort_order' => $request->validated('sort_order', 0),
        ]);

        return response()->json($image, 201);
    }

    public function update(UpdatePropertyImageRequest $request, string $id): JsonResponse
    {
        $image = PropertyImage::query()->findOrFail($id);

        $image->update($request->validated());

        return response()->json($image);
    }

    public function destroy(string $id): JsonResponse
    {
        $image = PropertyImage::query()->findOrFail($id);

        // Delete file from storage
        Storage::disk('public')->delete($image->image_path);

        $image->delete();

        return response()->json([
            'message' => 'Image deleted successfully.',
        ]);
    }
}
