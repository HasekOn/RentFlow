<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FindsMeter;
use App\Http\Controllers\Api\Concerns\FindsProperty;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMeterRequest;
use App\Http\Requests\UpdateMeterRequest;
use App\Http\Resources\MeterResource;
use App\Models\Meter;
use Illuminate\Http\JsonResponse;

class MeterController extends Controller
{
    use FindsMeter, FindsProperty;

    public function index(string $propertyId): JsonResponse
    {
        $property = $this->findProperty($propertyId);
        $this->authorize('view', $property);

        $meters = $property->meters()
            ->with(['readings' => function ($query) {
                $query->orderBy('reading_date', 'desc')->limit(5);
            }])
            ->get();

        return response()->json(MeterResource::collection($meters));
    }

    public function store(StoreMeterRequest $request, string $propertyId): JsonResponse
    {
        $property = $this->findProperty($propertyId);
        $this->authorize('create', [Meter::class, $property]);

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
        }, 'property'])->find($id);
        if ($meter === null) {
            abort(404);
        }
        $this->authorize('view', $meter);

        return response()->json(new MeterResource($meter));
    }

    public function update(UpdateMeterRequest $request, string $id): JsonResponse
    {
        $meter = $this->findMeter($id);
        $this->authorize('update', $meter);

        $meter->update($request->validated());

        return response()->json(new MeterResource($meter));
    }

    public function destroy(string $id): JsonResponse
    {
        $meter = $this->findMeter($id);
        $this->authorize('delete', $meter);

        $meter->delete();

        return response()->json([
            'message' => 'Meter deleted successfully.',
        ]);
    }
}
