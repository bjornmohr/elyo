<?php

namespace App\Http\Controllers\Company;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\InviteToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CompanyInvitationController extends Controller
{
    public function users(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;
        $user->loadMissing('roles');
        $isManager = $this->isManagerOnly($user);
        $managedTeamIds = $isManager ? $this->managedTeamIds($user) : [];

        $users = User::where('company_id', $companyId)
            ->when($isManager, fn ($query) => $query->whereIn('team_id', $managedTeamIds))
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
        $user = $request->user();
        $companyId = $user->company_id;
        $user->loadMissing('roles');

        $invites = InviteToken::where('company_id', $companyId)
            ->when($this->isManagerOnly($user), fn ($query) => $query
                ->where('invited_by_user_id', $user->id)
                ->where('role', Role::EMPLOYEE->value))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'email' => $i->email,
                'role' => $i->role->value,
                'teamId' => $i->team_id,
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
            'teamId' => [
                'nullable',
                'integer',
                Rule::exists('teams', 'id')->where(fn ($query) => $query->where('company_id', $request->user()->company_id)),
            ],
        ]);

        $user = $request->user();
        $companyId = $user->company_id;
        $role = Role::from($request->role);
        $teamId = $request->input('teamId');
        $teamId = $teamId === null ? null : (int) $teamId;

        if ($this->isManagerOnly($user)) {
            if ($role !== Role::EMPLOYEE) {
                return response()->json([
                    'error' => ['code' => 'FORBIDDEN', 'message' => 'Manager dürfen nur Mitarbeiter einladen.'],
                ], 403);
            }

            if ($teamId === null || ! in_array($teamId, $this->managedTeamIds($user), true)) {
                return response()->json([
                    'error' => ['code' => 'FORBIDDEN', 'message' => 'Manager dürfen nur in verwaltete Teams einladen.'],
                ], 403);
            }
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
            'team_id' => $teamId,
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
                'teamId' => $invite->team_id,
                'status' => $invite->status,
                'expires_at' => $invite->expires_at->toIso8601String(),
                'invite_token' => $rawToken, // DEV only
            ],
        ], 201);
    }

    public function destroyInvitation(Request $request, InviteToken $invite)
    {
        $companyId = $request->user()->company_id;
        $request->user()->loadMissing('roles');

        if ($invite->company_id !== $companyId) {
            return response()->json([
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Unauthorized.'],
            ], 403);
        }

        if ($this->isManagerOnly($request->user()) && ((int) $invite->invited_by_user_id !== (int) $request->user()->id || $invite->role !== Role::EMPLOYEE)) {
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

    private function isManagerOnly($user): bool
    {
        return $user->hasRole(Role::COMPANY_MANAGER) && ! $user->hasAnyRole([Role::COMPANY_ADMIN, Role::COMPANY_OWNER]);
    }

    private function managedTeamIds($user): array
    {
        return $user->managedTeams()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
