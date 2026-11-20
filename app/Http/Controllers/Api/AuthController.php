<?php

namespace App\Http\Controllers\Api;

use App\Actions\Users\RegisterUser;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request, RegisterUser $register): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(8)],
            // Správcem se uživatel stává jen povýšením od pronajímatele (ManagerController::promote).
            'role' => ['sometimes', Rule::in([Role::Landlord->value, Role::Tenant->value])],
        ]);

        $user = $register->handle($validated);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($validated)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $user = $this->currentUser($request);
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Smaže token, kterým se klient přihlásil. Bez Bearer tokenu (např. actingAs v testech) není co mazat.
        PersonalAccessToken::findToken((string) $request->bearerToken())?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
