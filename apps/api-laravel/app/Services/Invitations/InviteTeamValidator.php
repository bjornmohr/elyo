<?php

namespace App\Services\Invitations;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;

class InviteTeamValidator
{
    public function isManagerOnly(User $user): bool
    {
        return $user->hasRole(Role::COMPANY_MANAGER) && ! $user->hasAnyRole([Role::COMPANY_ADMIN, Role::COMPANY_OWNER]);
    }

    public function managedTeamIds(User $user): array
    {
        return $user->managedTeams()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function assertManagerCanInvite(User $user, Role $role, ?int $teamId): void
    {
        if (! $this->isManagerOnly($user)) {
            return;
        }

        if ($role !== Role::EMPLOYEE) {
            throw new InvitationDomainException(
                'FORBIDDEN',
                'Manager dürfen nur Mitarbeiter einladen.',
                403,
            );
        }

        if ($teamId === null || ! in_array($teamId, $this->managedTeamIds($user), true)) {
            throw new InvitationDomainException(
                'FORBIDDEN',
                'Manager dürfen nur in verwaltete Teams einladen.',
                403,
            );
        }
    }

    public function inviteTeamBelongsToCompany(int $teamId, int $companyId): bool
    {
        return Team::where('id', $teamId)
            ->where('company_id', $companyId)
            ->exists();
    }
}
