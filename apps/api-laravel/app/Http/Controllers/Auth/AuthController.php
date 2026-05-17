<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'requested_portal' => 'nullable|string|in:admin,company,employee,partner',
        ]);

        $user = User::with('roles', 'company')->where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Die Anmeldedaten sind ungültig.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['Die Anmeldedaten sind ungültig.'],
            ]);
        }

        // Validate portal access
        $requestedPortal = $request->input('requested_portal');
        $allowedPortals = $user->allowedPortals();

        if ($requestedPortal && ! $user->canUsePortal($requestedPortal)) {
            return response()->json([
                'error' => [
                    'code' => 'PORTAL_FORBIDDEN',
                    'message' => 'Sie haben keinen Zugang zu diesem Portal.',
                ],
            ], 403);
        }

        $activePortal = $requestedPortal && in_array($requestedPortal, $allowedPortals)
            ? $requestedPortal
            : ($allowedPortals[0] ?? null);

        $user->update(['last_login_at' => now()]);
        $user->load(['company', 'team']);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'roles' => $user->roleNames(),
                'teamId' => $user->team_id,
                'teamName' => $user->team?->name,
                'companyId' => $user->company_id,
                'companyName' => $user->company?->name,
            ],
            'activePortal' => $activePortal,
            'allowedPortals' => $allowedPortals,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(['company', 'roles']);

        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'roles' => $user->roleNames(),
            'companyId' => $user->company_id,
            'companyName' => $user->company?->name,
            'teamId' => $user->team_id,
            'teamName' => $user->team?->name,
            'allowedPortals' => $user->allowedPortals(),
        ]);
    }
}
