<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FindsMeter;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMeterReadingRequest;
use App\Http\Resources\MeterReadingResource;
use App\Models\MeterReading;
use Illuminate\Http\JsonResponse;

class MeterReadingController extends Controller
{
    use FindsMeter;

    public function index(string $meterId): JsonResponse
    {
        $meter = $this->findMeter($meterId);
        $this->authorize('view', $meter);

        $readings = $meter->readings()
            ->with('submittedBy')
            ->orderBy('reading_date', 'desc')
            ->get();

        return response()->json(MeterReadingResource::collection($readings));
    }

    public function store(StoreMeterReadingRequest $request, string $meterId): JsonResponse
    {
        $meter = $this->findMeter($meterId);
        $this->authorize('create', [MeterReading::class, $meter]);

        // Validate that new reading is not less than the last one
        $lastReading = $meter->readings()
            ->orderBy('reading_date', 'desc')
            ->first();

        if ($lastReading && $request->validated('reading_value') < $lastReading->reading_value) {
            return response()->json([
                'message' => 'Reading value cannot be less than the previous reading ('.$lastReading->reading_value.').',
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
        // odečet z jiného měřiče, než je v URL, je 404
        $reading = MeterReading::query()->with('meter.property')->where('meter_id', $meterId)->find($readingId);
        if ($reading === null) {
            abort(404);
        }
        $this->authorize('delete', $reading);

        $reading->delete();

        return response()->json([
            'message' => 'Reading deleted successfully.',
        ]);
    }
}
