<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMeterRequest;
use App\Http\Requests\UpdateMeterRequest;
use App\Http\Resources\MeterResource;
use App\Models\Meter;
use App\Models\Property;
use Illuminate\Http\JsonResponse;

class MeterController extends Controller
{
    public function index(string $propertyId): JsonResponse
    {
        $property = Property::query()->findOrFail($propertyId);

        $meters = $property->meters()
            ->with(['readings' => function ($query) {
                $query->orderBy('reading_date', 'desc')->limit(5);
            }])
            ->get();

        return response()->json(MeterResource::collection($meters));
    }

    public function store(StoreMeterRequest $request, string $propertyId): JsonResponse
    {
        $property = Property::query()->findOrFail($propertyId);

        if ($property->landlord_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You can only add meters to your own properties.',
            ], 403);
        }

        $meter = Meter::query()->create([
            ...$request->validated(),
            'property_id' => $property->id,
        ]);

        return response()->json(new MeterResource($meter), 201);
    }

    public function show(string $id): JsonResponse
    {
        $meter = Meter::query()->with(['readings' => function ($query) {
            $query->orderBy('reading_date', 'desc');
        }, 'property'])->findOrFail($id);

        return response()->json(new MeterResource($meter));
    }

    public function update(UpdateMeterRequest $request, string $id): JsonResponse
    {
        $meter = Meter::query()->findOrFail($id);

        $meter->update($request->validated());

        return response()->json(new MeterResource($meter));
    }

    public function destroy(string $id): JsonResponse
    {
        $meter = Meter::query()->findOrFail($id);

        $meter->delete();

        return response()->json([
            'message' => 'Meter deleted successfully.',
        ]);
    }
}
