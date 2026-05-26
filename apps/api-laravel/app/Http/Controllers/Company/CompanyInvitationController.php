<?php

namespace App\Http\Controllers\Company;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\InviteToken;
use App\Models\User;
use App\Services\Invitations\CompanyInvitationService;
use App\Services\Invitations\InvitationDomainException;
use App\Services\Invitations\InviteTeamValidator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyInvitationController extends Controller
{
    public function __construct(
        private readonly CompanyInvitationService $invitationService,
        private readonly InviteTeamValidator $teamValidator,
    ) {
    }

    public function users(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;
        $user->loadMissing('roles');
        $isManager = $this->teamValidator->isManagerOnly($user);
        $managedTeamIds = $isManager ? $this->teamValidator->managedTeamIds($user) : [];

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
            ->when($this->teamValidator->isManagerOnly($user), fn ($query) => $query
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

        try {
            $result = $this->invitationService->createInvitation(
                $request->user(),
                $request->only(['email', 'role', 'teamId']),
            );
        } catch (InvitationDomainException $exception) {
            return response()->json([
                'error' => ['code' => $exception->errorCode, 'message' => $exception->getMessage()],
            ], $exception->statusCode);
        }

        $invite = $result['invite'];

        return response()->json([
            'data' => [
                'id' => $invite->id,
                'email' => $invite->email,
                'role' => $invite->role->value,
                'teamId' => $invite->team_id,
                'status' => $invite->status,
                'expires_at' => $invite->expires_at->toIso8601String(),
                'invite_token' => $result['rawToken'], // DEV only
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

        if (
            $this->teamValidator->isManagerOnly($request->user())
            && ((int) $invite->invited_by_user_id !== (int) $request->user()->id || $invite->role !== Role::EMPLOYEE)
        ) {
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
