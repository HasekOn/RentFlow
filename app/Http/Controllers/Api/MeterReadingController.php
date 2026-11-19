<?php

namespace App\Http\Controllers\Api;

use App\Actions\Meters\CreateMeterReading;
use App\Actions\Meters\DeleteMeterReading;
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

    public function store(StoreMeterReadingRequest $request, string $meterId, CreateMeterReading $create): JsonResponse
    {
        $meter = $this->findMeter($meterId);
        $this->authorize('create', [MeterReading::class, $meter]);

        $reading = $create->handle($meter, $request->user(), $request->validated());

        $reading->load('submittedBy');

        return response()->json(new MeterReadingResource($reading), 201);
    }

    public function destroy(string $meterId, string $readingId, DeleteMeterReading $delete): JsonResponse
    {
        // odečet z jiného měřiče, než je v URL, je 404
        $reading = MeterReading::query()->with('meter.property')->where('meter_id', $meterId)->find($readingId);
        if ($reading === null) {
            abort(404);
        }
        $this->authorize('delete', $reading);

        $delete->handle($reading);

        return response()->json([
            'message' => 'Reading deleted successfully.',
        ]);
    }
}
