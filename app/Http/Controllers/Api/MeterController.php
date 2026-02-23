<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeterController extends Controller
{
    public function index(string $propertyId): JsonResponse
    {
        $property = Property::findOrFail($propertyId);

        $meters = $property->meters()
            ->with(['readings' => function ($query) {
                $query->orderBy('reading_date', 'desc')->limit(5);
            }])
            ->get();

        return response()->json($meters);
    }

    public function store(Request $request, string $propertyId): JsonResponse
    {
        $property = Property::findOrFail($propertyId);

        if ($property->landlord_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You can only add meters to your own properties.',
            ], 403);
        }

        $validated = $request->validate([
            'meter_type' => ['required', 'in:water,electricity,gas,heat'],
            'serial_number' => ['nullable', 'string'],
            'location' => ['nullable', 'string'],
        ]);

        $validated['property_id'] = $property->id;

        $meter = Meter::create($validated);

        return response()->json($meter, 201);
    }

    public function show(string $id): JsonResponse
    {
        $meter = Meter::with(['readings' => function ($query) {
            $query->orderBy('reading_date', 'desc');
        }, 'property'])->findOrFail($id);

        return response()->json($meter);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $meter = Meter::findOrFail($id);

        $validated = $request->validate([
            'meter_type' => ['sometimes', 'in:water,electricity,gas,heat'],
            'serial_number' => ['nullable', 'string'],
            'location' => ['nullable', 'string'],
        ]);

        $meter->update($validated);

        return response()->json($meter);
    }

    public function destroy(string $id): JsonResponse
    {
        $meter = Meter::findOrFail($id);
        $meter->delete();

        return response()->json([
            'message' => 'Meter deleted successfully.',
        ]);
    }
}
