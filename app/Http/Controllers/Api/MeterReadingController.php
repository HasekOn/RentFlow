<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use App\Models\MeterReading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeterReadingController extends Controller
{
    public function index(string $meterId): JsonResponse
    {
        $meter = Meter::findOrFail($meterId);

        $readings = $meter->readings()
            ->with('submittedBy')
            ->orderBy('reading_date', 'desc')
            ->get();

        return response()->json($readings);
    }

    public function store(Request $request, string $meterId): JsonResponse
    {
        $meter = Meter::findOrFail($meterId);

        $validated = $request->validate([
            'reading_value' => ['required', 'numeric', 'min:0'],
            'reading_date' => ['required', 'date'],
            'photo_proof' => ['nullable', 'string'],
        ]);

        // Validate that new reading is not less than the last one
        $lastReading = $meter->readings()
            ->orderBy('reading_date', 'desc')
            ->first();

        if ($lastReading && $validated['reading_value'] < $lastReading->reading_value) {
            return response()->json([
                'message' => 'Reading value cannot be less than the previous reading (' . $lastReading->reading_value . ').',
            ], 422);
        }

        $validated['meter_id'] = $meter->id;
        $validated['submitted_by'] = $request->user()->id;

        $reading = MeterReading::create($validated);
        $reading->load('submittedBy');

        return response()->json($reading, 201);
    }

    public function destroy(string $meterId, string $readingId): JsonResponse
    {
        $reading = MeterReading::where('meter_id', $meterId)
            ->findOrFail($readingId);

        $reading->delete();

        return response()->json([
            'message' => 'Reading deleted successfully.',
        ]);
    }
}
