<?php

namespace App\Services\Invitations;

use App\Models\InviteToken;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\DB;

/**
 * Invite acceptance is an identity-domain operation only.
 *
 * It deliberately does NOT provision a health subject. Prompt 05 originally
 * provisioned synchronously here; the runtime split (ADR-003 D2) superseded
 * that, because `/api/auth/invite/accept` is served by the identity runtime,
 * which holds no mapping connection and no mapping key material — so a
 * health-domain write from this class is a boundary violation, not an
 * optimisation.
 *
 * Provisioning is idempotent and happens where the credentials legitimately
 * exist: `App\Services\Health\ResolvesOwnSubject` provisions on the employee
 * runtime at first health access, and `elyo:provision-subjects` backfills in
 * bulk. A user who never opens a health feature never gets a health-domain
 * row, which is the better data-minimisation outcome.
 */
class InviteAcceptanceService
{
    public function __construct(
        private readonly InviteTeamValidator $teamValidator,
    ) {}

    public function accept(string $token, string $name, string $password): User
    {
        $invite = InviteToken::with('company')
            ->where('token_hash', hash('sha256', $token))
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if (! $invite) {
            throw new InvitationDomainException(
                'INVALID_INVITE',
                'Einladung ungültig oder abgelaufen.',
            );
        }

        if (
            $invite->team_id !== null
            && ! $this->teamValidator->inviteTeamBelongsToCompany((int) $invite->team_id, (int) $invite->company_id)
        ) {
            throw new InvitationDomainException(
                'INVALID_INVITE_TEAM',
                'Die Team-Zuordnung dieser Einladung ist ungültig.',
            );
        }

        $existingUser = User::where('email', $invite->email)->first();

        if ($existingUser) {
            if ($existingUser->company_id && $invite->company_id && $existingUser->company_id !== $invite->company_id) {
                throw new InvitationDomainException(
                    'COMPANY_CONFLICT',
                    'Dieses Konto gehört bereits zu einem anderen Unternehmen.',
                );
            }

            if (
                $invite->team_id !== null
                && $existingUser->team_id !== null
                && (int) $existingUser->team_id !== (int) $invite->team_id
            ) {
                throw new InvitationDomainException(
                    'TEAM_CONFLICT',
                    'Dieses Konto ist bereits einem anderen Team zugeordnet.',
                );
            }

            return DB::transaction(function () use ($existingUser, $invite) {
                if (! $existingUser->hasRole($invite->role)) {
                    UserRole::create([
                        'user_id' => $existingUser->id,
                        'role' => $invite->role,
                    ]);
                }

                if ($invite->team_id !== null && $existingUser->team_id === null) {
                    $existingUser->update(['team_id' => $invite->team_id]);
                }

                $invite->update(['status' => 'accepted', 'accepted_at' => now()]);

                return $existingUser->load('roles', 'company');
            });
        }

        $user = DB::transaction(function () use ($invite, $name, $password) {
            $user = User::create([
                'name' => $name,
                'email' => $invite->email,
                'password' => $password,
                'company_id' => $invite->company_id,
                'team_id' => $invite->team_id,
            ]);

            UserRole::create([
                'user_id' => $user->id,
                'role' => $invite->role,
            ]);

            $invite->update(['status' => 'accepted', 'accepted_at' => now()]);

            return $user->load('roles', 'company');
        });

        return $user;
    }
}
