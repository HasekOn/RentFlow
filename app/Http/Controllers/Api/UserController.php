<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Lidé, které přihlášený vidí (User::visibleTo), volitelně podle role.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $validated = $request->validate([
            'role' => ['sometimes', Rule::enum(Role::class)],
        ]);

        $users = User::query()
            ->visibleTo($request->user())
            ->when(isset($validated['role']), fn ($query) => $query->where('role', $validated['role']))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'phone', 'trust_score', 'created_at']);

        return response()->json($users);
    }

    /**
     * Vyhledání účtu nájemníka nebo správce podle přesného e-mailu pro novou smlouvu. Vrací jen minimum údajů.
     */
    public function lookup(Request $request): JsonResponse
    {
        $this->authorize('lookup', User::class);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->whereIn('role', [Role::Tenant, Role::Manager])
            ->first(['id', 'name', 'email', 'role']);
        if ($user === null) {
            abort(404);
        }

        return response()->json($user);
    }

    /**
     * Hodnocení nájemníka ze všech jeho smluv (i smazaných).
     */
    public function ratings(Request $request, string $id): JsonResponse
    {
        $user = User::query()->find($id);
        if ($user === null) {
            abort(404);
        }
        $this->authorize('view', $user);

        $ratings = Rating::query()
            ->whereIn('lease_id', Lease::withTrashed()->select('id')->where('tenant_id', $user->id))
            ->with(['ratedBy', 'lease.property'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Rating $rating) => [
                'id' => $rating->id,
                'category' => $rating->category,
                'score' => $rating->score,
                'comment' => $rating->comment,
                'created_at' => $rating->created_at->toDateTimeString(),
                'rated_by' => $rating->ratedBy ? ['id' => $rating->ratedBy->id, 'name' => $rating->ratedBy->name] : null,
                'property' => $rating->lease?->property ? ['address' => $rating->lease->property->address] : null,
            ]);

        return response()->json($ratings);
    }
}
