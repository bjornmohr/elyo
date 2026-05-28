<?php

namespace App\Services\Invitations;

use App\Enums\Role;
use App\Models\InviteToken;
use App\Models\User;
use Illuminate\Support\Str;

class CompanyInvitationService
{
    public function __construct(private readonly InviteTeamValidator $teamValidator)
    {
    }

    /**
     * @param  array{email: string, role: string, teamId?: int|null}  $payload
     * @return array{invite: InviteToken, rawToken: string}
     */
    public function createInvitation(User $user, array $payload): array
    {
        $companyId = $user->company_id;
        $role = Role::from($payload['role']);
        $teamId = $payload['teamId'] ?? null;
        $teamId = $teamId === null ? null : (int) $teamId;
        $teamLayerEnabled = (bool) $user->company()->value('team_layer_enabled');

        if (! $teamLayerEnabled && ($this->teamValidator->isManagerOnly($user) || $role === Role::COMPANY_MANAGER)) {
            throw new InvitationDomainException(
                'TEAM_LAYER_DISABLED',
                'Manager-Einladungen sind nur bei aktivierter Team-Ebene möglich.',
                403,
            );
        }

        if (! $teamLayerEnabled && $teamId !== null) {
            throw new InvitationDomainException(
                'TEAM_LAYER_DISABLED',
                'Teamzuordnung ist für dieses Unternehmen deaktiviert.',
            );
        }

        $this->teamValidator->assertManagerCanInvite($user, $role, $teamId);

        $existingUser = User::where('email', $payload['email'])->first();
        if ($existingUser && $existingUser->company_id && $existingUser->company_id !== $companyId) {
            throw new InvitationDomainException(
                'COMPANY_CONFLICT',
                'Diese E-Mail gehört bereits zu einem anderen Unternehmen.',
            );
        }

        $rawToken = Str::random(64);

        $invite = InviteToken::create([
            'company_id' => $companyId,
            'team_id' => $teamId,
            'email' => $payload['email'],
            'role' => $role,
            'token_hash' => hash('sha256', $rawToken),
            'invited_by_user_id' => $user->id,
            'expires_at' => now()->addDays(7),
        ]);

        return ['invite' => $invite, 'rawToken' => $rawToken];
    }
}
