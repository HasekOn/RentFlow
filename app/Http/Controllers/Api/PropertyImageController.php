<?php

namespace App\Http\Controllers\Api;

use App\Actions\Properties\DeletePropertyImage;
use App\Actions\Properties\StorePropertyImage;
use App\Actions\Properties\UpdatePropertyImage;
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

    public function store(StorePropertyImageRequest $request, string $propertyId, StorePropertyImage $store): JsonResponse
    {
        $property = $this->findProperty($propertyId);
        $this->authorize('create', [PropertyImage::class, $property]);

        $image = $store->handle($property, $request->user(), $request->file('image'), $request->safe()->except('image'));

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

    public function update(UpdatePropertyImageRequest $request, string $id, UpdatePropertyImage $update): JsonResponse
    {
        $image = $this->findImage($id);
        $this->authorize('update', $image);

        $update->handle($image, $request->validated());

        return response()->json(new PropertyImageResource($image));
    }

    public function destroy(string $id, DeletePropertyImage $delete): JsonResponse
    {
        $image = $this->findImage($id);
        $this->authorize('delete', $image);

        $delete->handle($image);

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
