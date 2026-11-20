<?php

namespace App\Http\Controllers\Api;

use App\Actions\Managers\AssignManager;
use App\Actions\Managers\RemoveManager;
use App\Http\Controllers\Api\Concerns\FindsProperty;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Správci podle rozhodnutí z auditu (#14): povýšení = přiřazení k nemovitosti pronajímatele, sesazení odebere jen
 * jeho přiřazení, users.role se přepočítá (SyncManagerRole). Povýšit lze jen koho pronajímatel vidí.
 */
class ManagerController extends Controller
{
    use FindsProperty;

    /**
     * POST /api/v1/users/{user}/promote-manager (property_id vlastní nemovitosti)
     */
    public function promote(Request $request, string $userId, AssignManager $assign): JsonResponse
    {
        $user = $this->findUser($userId);
        $this->authorize('promote', $user);

        $request->validate(['property_id' => ['required', 'integer']]);
        $property = $this->findProperty((string) $request->input('property_id'));
        $this->authorize('assignManager', $property);

        $assign->handle($property, $user);

        return response()->json([
            'message' => 'User promoted to manager.',
            'user' => UserResource::nested($user, $request),
        ]);
    }

    /**
     * POST /api/v1/users/{user}/demote-manager – odebere ze všech nemovitostí přihlášeného pronajímatele.
     */
    public function demote(Request $request, string $userId, RemoveManager $remove): JsonResponse
    {
        $user = $this->findUser($userId);
        $this->authorize('demote', $user);

        /** @var list<int> $propertyIds */
        $propertyIds = Property::withTrashed()
            ->where('landlord_id', $this->currentUser($request)->id)
            ->whereHas('managers', fn ($manager) => $manager->whereKey($user->id))
            ->pluck('id')
            ->all();

        if ($propertyIds === []) {
            return response()->json(['message' => 'User is not a manager of your properties.'], 422);
        }

        $remove->handle($user, $propertyIds);

        return response()->json([
            'message' => 'Manager removed from your properties.',
            'user' => UserResource::nested($user, $request),
        ]);
    }

    /**
     * POST /api/v1/properties/{property}/assign-manager
     */
    public function assign(Request $request, string $propertyId, AssignManager $assign): JsonResponse
    {
        $property = $this->findProperty($propertyId);
        $this->authorize('assignManager', $property);

        $request->validate(['user_id' => ['required', 'integer']]);
        $user = $this->findUser((string) $request->input('user_id'));
        $this->authorize('promote', $user);

        $assign->handle($property, $user);

        return response()->json([
            'message' => 'Manager assigned to property.',
            'managers' => $this->managers($property),
        ]);
    }

    /**
     * DELETE /api/v1/properties/{property}/remove-manager/{user}
     */
    public function remove(string $propertyId, string $userId, RemoveManager $remove): JsonResponse
    {
        $property = $this->findProperty($propertyId);
        $this->authorize('assignManager', $property);

        // jen skutečně přiřazený správce, jinak by šlo přepočítat roli komukoli podle id
        $user = $property->managers()->whereKey($userId)->first();
        if ($user === null) {
            abort(404);
        }

        $remove->handle($user, [$property->id]);

        return response()->json([
            'message' => 'Manager removed from property.',
            'managers' => $this->managers($property),
        ]);
    }

    /**
     * GET /api/v1/properties/{property}/managers – kontakty správců jen vlastníkovi (#18)
     */
    public function list(string $propertyId): JsonResponse
    {
        $property = $this->findProperty($propertyId);
        $this->authorize('assignManager', $property);

        return response()->json(
            $property->managers()->get(['users.id', 'users.name', 'users.email', 'users.phone'])
        );
    }

    private function findUser(string $id): User
    {
        $user = User::query()->find($id);
        if ($user === null) {
            abort(404);
        }

        return $user;
    }

    /**
     * @return Collection<int, User>
     */
    private function managers(Property $property): Collection
    {
        return $property->managers()->get(['users.id', 'users.name', 'users.email']);
    }
}
