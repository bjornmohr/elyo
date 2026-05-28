<?php

namespace App\Services\Company;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class TeamLayerGuard
{
    public function enabledFor(User $user): bool
    {
        return (bool) $user->company()->value('team_layer_enabled');
    }

    public function isManagerOnly(User $user): bool
    {
        $user->loadMissing('roles');

        return $user->hasRole(Role::COMPANY_MANAGER)
            && ! $user->hasAnyRole([Role::COMPANY_ADMIN, Role::COMPANY_OWNER]);
    }

    public function abortIfDisabled(User $user, int $status = 403): void
    {
        if ($this->enabledFor($user)) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'TEAM_LAYER_DISABLED',
                'message' => 'Team-Ebene ist für dieses Unternehmen deaktiviert.',
            ],
        ], $status));
    }

    public function abortManagerWorkflowIfDisabled(User $user): void
    {
        if ($this->isManagerOnly($user)) {
            $this->abortIfDisabled($user);
        }
    }
}
