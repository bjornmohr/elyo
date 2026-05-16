<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\InviteToken;
use App\Models\User;
use App\Enums\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CompanyInvitationController extends Controller
{
    public function users(Request $request)
    {
        $companyId = $request->user()->company_id;

        $users = User::where('company_id', $companyId)
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'roles' => $u->roleNames(),
                'status' => $u->status,
                'lastLoginAt' => $u->last_login_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $users]);
    }

    public function invitations(Request $request)
    {
        $companyId = $request->user()->company_id;

        $invites = InviteToken::where('company_id', $companyId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'email' => $i->email,
                'role' => $i->role->value,
                'status' => $i->status,
                'expiresAt' => $i->expires_at->toIso8601String(),
                'createdAt' => $i->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $invites]);
    }

    public function storeInvitation(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'role' => 'required|string|in:COMPANY_ADMIN,COMPANY_MANAGER,EMPLOYEE',
        ]);

        $user = $request->user();
        $companyId = $user->company_id;
        $role = Role::from($request->role);

        // COMPANY_MANAGER can only invite EMPLOYEE
        if ($user->hasRole(Role::COMPANY_MANAGER) && $role !== Role::EMPLOYEE) {
            return response()->json([
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Sie dürfen nur Mitarbeiter einladen.'],
            ], 403);
        }

        // Check if email already belongs to a different company
        $existingUser = User::where('email', $request->email)->first();
        if ($existingUser && $existingUser->company_id && $existingUser->company_id !== $companyId) {
            return response()->json([
                'error' => ['code' => 'COMPANY_CONFLICT', 'message' => 'Diese E-Mail gehört bereits zu einem anderen Unternehmen.'],
            ], 422);
        }

        $rawToken = Str::random(64);

        $invite = InviteToken::create([
            'company_id' => $companyId,
            'email' => $request->email,
            'role' => $role,
            'token_hash' => hash('sha256', $rawToken),
            'invited_by_user_id' => $user->id,
            'expires_at' => now()->addDays(7),
        ]);

        return response()->json([
            'data' => [
                'id' => $invite->id,
                'email' => $invite->email,
                'role' => $invite->role->value,
                'status' => $invite->status,
                'expires_at' => $invite->expires_at->toIso8601String(),
                'invite_token' => $rawToken, // DEV only
            ],
        ], 201);
    }

    public function destroyInvitation(Request $request, InviteToken $invite)
    {
        $companyId = $request->user()->company_id;

        if ($invite->company_id !== $companyId) {
            return response()->json([
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Unauthorized.'],
            ], 403);
        }

        if ($invite->status !== 'pending') {
            return response()->json([
                'error' => ['code' => 'INVALID_STATE', 'message' => 'Einladung kann nicht mehr gelöscht werden.'],
            ], 422);
        }

        $invite->update(['status' => 'revoked']);

        return response()->json(['message' => 'Einladung widerrufen.']);
    }
}
