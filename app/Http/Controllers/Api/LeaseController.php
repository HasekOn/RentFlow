<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'landlord') {
            $leases = Lease::whereIn(
                'property_id',
                $user->ownedProperties()->pluck('id')
            )->with(['property', 'tenant'])->get();
        } else {
            $leases = $user->leases()->with('property')->get();
        }

        return response()->json($leases);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'tenant_id' => ['required', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'rent_amount' => ['required', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'utility_advances' => ['nullable', 'numeric', 'min:0'],
            'variable_symbol' => ['nullable', 'string', 'max:20', 'unique:leases'],
        ]);

        $property = Property::findOrFail($validated['property_id']);

        if ($property->landlord_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You can only create leases for your own properties.',
            ], 403);
        }

        $lease = Lease::create($validated);

        $lease->load(['property', 'tenant']);

        return response()->json($lease, 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $lease = Lease::with([
            'property',
            'tenant',
            'payments',
            'ratings',
        ])->findOrFail($id);

        return response()->json($lease);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $lease = Lease::findOrFail($id);

        $validated = $request->validate([
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'rent_amount' => ['sometimes', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'utility_advances' => ['nullable', 'numeric', 'min:0'],
            'variable_symbol' => ['nullable', 'string', 'max:20', 'unique:leases,variable_symbol,' . $lease->id],
            'status' => ['sometimes', 'in:active,ended,terminated'],
        ]);

        $lease->update($validated);

        return response()->json($lease);
    }

    public function destroy(string $id): JsonResponse
    {
        $lease = Lease::findOrFail($id);
        $lease->delete();

        return response()->json([
            'message' => 'Lease deleted successfully.',
        ]);
    }
}
