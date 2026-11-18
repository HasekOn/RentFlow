<?php

namespace App\Http\Controllers\Api;

use App\Enums\PropertyImageType;
use App\Http\Controllers\Api\Concerns\FindsProperty;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePropertyImageRequest;
use App\Http\Requests\UpdatePropertyImageRequest;
use App\Http\Resources\PropertyImageResource;
use App\Models\PropertyImage;
use App\Support\PrivateFile;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyImageController extends Controller
{
    use FindsProperty;

    public function index(string $propertyId): JsonResponse
    {
        $property = $this->findProperty($propertyId);
        $this->authorize('view', $property);

        $images = $property->images()
            ->orderBy('sort_order')
            ->get();

        return response()->json(PropertyImageResource::collection($images));
    }

    public function store(StorePropertyImageRequest $request, string $propertyId): JsonResponse
    {
        $property = $this->findProperty($propertyId);
        $this->authorize('create', [PropertyImage::class, $property]);

        $path = $request->file('image')->store('properties/'.$property->id, PrivateFile::DISK);

        $image = PropertyImage::query()->create([
            'property_id' => $property->id,
            'image_path' => $path,
            'type' => $request->validated('type', PropertyImageType::Marketing->value),
            'uploaded_by' => $request->user()->id,
            'description' => $request->validated('description'),
            'sort_order' => $request->validated('sort_order', 0),
        ]);

        return response()->json(new PropertyImageResource($image), 201);
    }

    public function file(string $id): StreamedResponse
    {
        $image = $this->findImage($id);
        if ($image->property === null) {
            abort(404);
        }

        $this->authorize('view', $image->property);

        return PrivateFile::download($image->image_path, basename($image->image_path));
    }

    public function update(UpdatePropertyImageRequest $request, string $id): JsonResponse
    {
        $image = $this->findImage($id);
        $this->authorize('update', $image);

        $image->update($request->validated());

        return response()->json(new PropertyImageResource($image));
    }

    public function destroy(string $id): JsonResponse
    {
        $image = $this->findImage($id);
        $this->authorize('delete', $image);

        PrivateFile::delete($image->image_path);
        $image->delete();

        return response()->json([
            'message' => 'Image deleted successfully.',
        ]);
    }

    private function findImage(string $id): PropertyImage
    {
        $image = PropertyImage::query()->with('property')->find($id);
        if ($image === null) {
            abort(404);
        }

        return $image;
    }
}
