<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PropertyImageController extends Controller
{
    public function index(string $propertyId): JsonResponse
    {
        $property = Property::findOrFail($propertyId);

        $images = $property->images()
            ->orderBy('sort_order')
            ->get();

        return response()->json($images);
    }

    public function store(Request $request, string $propertyId): JsonResponse
    {
        $property = Property::findOrFail($propertyId);

        $validated = $request->validate([
            'image' => ['required', 'image', 'max:5120'], // max 5MB
            'type' => ['sometimes', 'in:marketing,defect,document'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        // Store file in storage/app/public/properties/{id}/
        $path = $request->file('image')->store(
            'properties/' . $property->id,
            'public'
        );

        $image = PropertyImage::create([
            'property_id' => $property->id,
            'image_path' => $path,
            'type' => $validated['type'] ?? 'marketing',
            'uploaded_by' => $request->user()->id,
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json($image, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $image = PropertyImage::findOrFail($id);

        $validated = $request->validate([
            'type' => ['sometimes', 'in:marketing,defect,document'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $image->update($validated);

        return response()->json($image);
    }

    public function destroy(string $id): JsonResponse
    {
        $image = PropertyImage::findOrFail($id);

        // Delete file from storage
        Storage::disk('public')->delete($image->image_path);

        $image->delete();

        return response()->json([
            'message' => 'Image deleted successfully.',
        ]);
    }
}
