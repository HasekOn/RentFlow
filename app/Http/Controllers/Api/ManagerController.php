<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagerController extends Controller
{
    /**
     * Promote user to manager role
     * POST /api/v1/users/{user}/promote-manager
     */
    public function promote(Request $request, string $userId): JsonResponse
    {
        $landlord = $request->user();

        if ($landlord->role !== 'landlord') {
            return response()->json(['message' => 'Only landlords can promote managers.'], 403);
        }

        $user = User::query()->findOrFail($userId);

        if ($user->role === 'landlord') {
            return response()->json(['message' => 'Cannot promote a landlord.'], 422);
        }

        $user->update(['role' => 'manager']);

        return response()->json(['message' => 'User promoted to manager.', 'user' => $user]);
    }

    /**
     * Demote manager back to tenant
     * POST /api/v1/users/{user}/demote-manager
     */
    public function demote(Request $request, string $userId): JsonResponse
    {
        $landlord = $request->user();

        if ($landlord->role !== 'landlord') {
            return response()->json(['message' => 'Only landlords can demote managers.'], 403);
        }

        $user = User::query()->findOrFail($userId);

        if ($user->role !== 'manager') {
            return response()->json(['message' => 'User is not a manager.'], 422);
        }

        // Remove all property assignments
        $user->managedProperties()->detach();
        $user->update(['role' => 'tenant']);

        return response()->json(['message' => 'Manager demoted to tenant.', 'user' => $user]);
    }

    /**
     * Assign manager to property
     * POST /api/v1/properties/{property}/assign-manager
     */
    public function assign(Request $request, string $propertyId): JsonResponse
    {
        $landlord = $request->user();
        $property = Property::query()->findOrFail($propertyId);

        if ($property->landlord_id !== $landlord->id) {
            return response()->json(['message' => 'You can only assign managers to your own properties.'], 403);
        }

        $request->validate(['user_id' => 'required|exists:users,id']);

        $manager = User::query()->findOrFail($request->input('user_id'));

        if ($manager->role !== 'manager') {
            return response()->json(['message' => 'User must be a manager first.'], 422);
        }

        $property->managers()->syncWithoutDetaching([$manager->id]);

        return response()->json([
            'message' => 'Manager assigned to property.',
            'managers' => $property->managers()->get(['users.id', 'users.name', 'users.email']),
        ]);
    }

    /**
     * Remove manager from property
     * DELETE /api/v1/properties/{property}/remove-manager/{user}
     */
    public function remove(Request $request, string $propertyId, string $userId): JsonResponse
    {
        $landlord = $request->user();
        $property = Property::query()->findOrFail($propertyId);

        if ($property->landlord_id !== $landlord->id) {
            return response()->json(['message' => 'You can only manage your own properties.'], 403);
        }

        $property->managers()->detach($userId);

        return response()->json([
            'message' => 'Manager removed from property.',
            'managers' => $property->managers()->get(['users.id', 'users.name', 'users.email']),
        ]);
    }

    /**
     * List managers for a property
     * GET /api/v1/properties/{property}/managers
     */
    public function list(Request $request, string $propertyId): JsonResponse
    {
        $property = Property::query()->findOrFail($propertyId);

        return response()->json(
            $property->managers()->get(['users.id', 'users.name', 'users.email', 'users.phone'])
        );
    }
}
