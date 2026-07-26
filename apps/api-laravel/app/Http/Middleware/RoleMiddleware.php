<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (! $request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Unauthorized.']], 403);
        }

        $user->loadMissing('roles');

        $hasRole = $user->roles->contains(fn ($ur) => in_array($ur->role->value, $roles));

        if (! $hasRole) {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Unauthorized.']], 403);
        }

        return $next($request);
    }
}
