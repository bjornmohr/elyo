<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\InviteToken;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class InviteController extends Controller
{
    public function verify(Request $request)
    {
        $request->validate(['token' => 'required|string']);

        $tokenHash = hash('sha256', $request->input('token'));

        $invite = InviteToken::with('company')
            ->where('token_hash', $tokenHash)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if (! $invite) {
            return response()->json([
                'valid' => false,
                'error' => 'Einladung ungültig oder abgelaufen.',
            ], 404);
        }

        return response()->json([
            'valid' => true,
            'email' => $invite->email,
            'companyName' => $invite->company?->name,
            'role' => $invite->role->value,
            'expiresAt' => $invite->expires_at->toIso8601String(),
        ]);
    }

    public function accept(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'name' => 'required|string|max:255',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $tokenHash = hash('sha256', $request->input('token'));

        $invite = InviteToken::with('company')
            ->where('token_hash', $tokenHash)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if (! $invite) {
            return response()->json([
                'error' => ['code' => 'INVALID_INVITE', 'message' => 'Einladung ungültig oder abgelaufen.'],
            ], 422);
        }

        // Check if user already exists
        $existingUser = User::where('email', $invite->email)->first();

        if ($existingUser) {
            // User exists with a different company — reject
            if ($existingUser->company_id && $invite->company_id && $existingUser->company_id !== $invite->company_id) {
                return response()->json([
                    'error' => ['code' => 'COMPANY_CONFLICT', 'message' => 'Dieses Konto gehört bereits zu einem anderen Unternehmen.'],
                ], 422);
            }

            // User exists in same company — add missing role
            if (! $existingUser->hasRole($invite->role)) {
                UserRole::create([
                    'user_id' => $existingUser->id,
                    'role' => $invite->role,
                ]);
            }

            $invite->update(['status' => 'accepted', 'accepted_at' => now()]);

            $existingUser->load('roles', 'company');
            $token = $existingUser->createToken('auth_token')->plainTextToken;

            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $existingUser->id,
                    'email' => $existingUser->email,
                    'name' => $existingUser->name,
                    'roles' => $existingUser->roleNames(),
                    'companyId' => $existingUser->company_id,
                    'teamId' => $existingUser->team_id,
                ],
            ]);
        }

        // Create new user
        $user = DB::transaction(function () use ($invite, $request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $invite->email,
                'password' => $request->password,
                'company_id' => $invite->company_id,
                'team_id' => $invite->team_id,
            ]);

            UserRole::create([
                'user_id' => $user->id,
                'role' => $invite->role,
            ]);

            $invite->update(['status' => 'accepted', 'accepted_at' => now()]);

            return $user;
        });

        $user->load('roles', 'company');
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'roles' => $user->roleNames(),
                'companyId' => $user->company_id,
                'teamId' => $user->team_id,
            ],
        ]);
    }
}
