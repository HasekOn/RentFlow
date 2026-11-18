<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     * Usage: middleware('role:landlord') or middleware('role:landlord,manager')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        $allowed = array_map(fn (string $role): Role => Role::from($role), $roles);

        // Hláška záměrně neprozrazuje, které role mají přístup.
        if (! $user || ! in_array($user->role, $allowed, true)) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        return $next($request);
    }
}
