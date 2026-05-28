<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PortalMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $portal): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user->loadMissing('roles');

        if (! $user->canUsePortal($portal)) {
            return response()->json([
                'error' => [
                    'code' => 'PORTAL_FORBIDDEN',
                    'message' => 'Sie haben keinen Zugang zu diesem Portal.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
