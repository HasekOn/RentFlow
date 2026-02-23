<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMeterReadingRequest;
use App\Http\Resources\MeterReadingResource;
use App\Models\Meter;
use App\Models\MeterReading;
use Illuminate\Http\JsonResponse;

class MeterReadingController extends Controller
{
    public function index(string $meterId): JsonResponse
    {
        $meter = Meter::query()->findOrFail($meterId);

        $readings = $meter->readings()
            ->with('submittedBy')
            ->orderBy('reading_date', 'desc')
            ->get();

        return response()->json(MeterReadingResource::collection($readings));
    }

    public function store(StoreMeterReadingRequest $request, string $meterId): JsonResponse
    {
        $meter = Meter::query()->findOrFail($meterId);

        // Validate that new reading is not less than the last one
        $lastReading = $meter->readings()
            ->orderBy('reading_date', 'desc')
            ->first();

        if ($lastReading && $request->validated('reading_value') < $lastReading->reading_value) {
            return response()->json([
                'message' => 'Reading value cannot be less than the previous reading (' . $lastReading->reading_value . ').',
            ], 422);
        }

        $reading = MeterReading::query()->create([
            ...$request->validated(),
            'meter_id' => $meter->id,
            'submitted_by' => $request->user()->id,
        ]);

        $reading->load('submittedBy');

        return response()->json(new MeterReadingResource($reading), 201);
    }

    public function destroy(string $meterId, string $readingId): JsonResponse
    {
        $reading = MeterReading::query()->where('meter_id', $meterId)
            ->findOrFail($readingId);

        $reading->delete();

        return response()->json([
            'message' => 'Reading deleted successfully.',
        ]);
    }
}
