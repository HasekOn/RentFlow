<?php

namespace App\Http\Controllers\Api;

use App\Actions\Users\ChangePassword;
use App\Actions\Users\UpdateProfile;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function changePassword(Request $request, ChangePassword $change): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ]);

        // token, kterým se heslo mění, zůstane, ostatní se smažou
        $change->handle(
            $request->user(),
            $validated['current_password'],
            $validated['password'],
            PersonalAccessToken::findToken((string) $request->bearerToken())?->id,
        );

        return response()->json(['message' => 'Password changed successfully.']);
    }

    public function update(Request $request, UpdateProfile $update): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $update->handle($request->user(), $validated);

        return response()->json($request->user()->fresh());
    }
}
