<?php

namespace App\Services\Company;

use App\Enums\Role;
use App\Models\Measure;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CompanyMeasureAccessService
{
    public function __construct(private readonly TeamLayerGuard $teamLayerGuard)
    {
    }

    public function readableMeasureFor(User $user, string|int $measureId): Measure
    {
        return $this->readableMeasureQueryFor($user)
            ->whereKey($measureId)
            ->firstOrFail();
    }

    public function manageableMeasureFor(User $user, string|int $measureId): Measure
    {
        $isManager = $this->isManagerOnly($user);
        $teamLayerEnabled = $this->teamLayerGuard->enabledFor($user);

        if (! $teamLayerEnabled && $isManager) {
            $this->teamLayerGuard->abortIfDisabled($user);
        }

        $measure = Measure::query()
            ->where('company_id', $user->company_id)
            ->whereKey($measureId)
            ->firstOrFail();

        if (! $teamLayerEnabled && $measure->team_id !== null) {
            $this->teamLayerGuard->abortIfDisabled($user);
        }

        if ($isManager) {
            $managedTeamId = $this->managedTeamIdFor($user);
            if (! $managedTeamId || (int) $measure->team_id !== $managedTeamId) {
                abort(403);
            }
        }

        return $measure;
    }

    /**
     * Company measure read visibility: admins/owners can read all visible
     * company measures, managers can read company-wide and own-team measures.
     *
     * @return Builder<Measure>
     */
    public function readableMeasureQueryFor(User $user): Builder
    {
        $isManager = $this->isManagerOnly($user);
        $teamLayerEnabled = $this->teamLayerGuard->enabledFor($user);

        if (! $teamLayerEnabled && $isManager) {
            $this->teamLayerGuard->abortIfDisabled($user);
        }

        $query = Measure::query()->where('company_id', $user->company_id);

        if (! $teamLayerEnabled) {
            $query->whereNull('team_id');
        }

        if ($isManager) {
            $managedTeamId = $this->managedTeamIdFor($user);
            if (! $managedTeamId) {
                return $query->whereRaw('1 = 0');
            }

            $query->where(fn (Builder $q) => $q
                ->whereNull('team_id')
                ->orWhere('team_id', $managedTeamId)
            );
        }

        return $query;
    }

    /**
     * Company measure write visibility: managers can manage own-team measures
     * only; company-wide measures remain admin/owner managed.
     *
     * @return Builder<Measure>
     */
    public function manageableMeasureQueryFor(User $user): Builder
    {
        $isManager = $this->isManagerOnly($user);
        $teamLayerEnabled = $this->teamLayerGuard->enabledFor($user);

        if (! $teamLayerEnabled && $isManager) {
            $this->teamLayerGuard->abortIfDisabled($user);
        }

        $query = Measure::query()->where('company_id', $user->company_id);

        if (! $teamLayerEnabled) {
            $query->whereNull('team_id');
        }

        if ($isManager) {
            $managedTeamId = $this->managedTeamIdFor($user);
            if (! $managedTeamId) {
                return $query->whereRaw('1 = 0');
            }

            $query->where('team_id', $managedTeamId);
        }

        return $query;
    }

    private function isManagerOnly(User $user): bool
    {
        $user->loadMissing('roles');

        return $user->hasRole(Role::COMPANY_MANAGER)
            && ! $user->hasAnyRole([Role::COMPANY_ADMIN, Role::COMPANY_OWNER]);
    }

    private function managedTeamIdFor(User $user): ?int
    {
        return Team::where('manager_id', $user->id)
            ->where('company_id', $user->company_id)
            ->value('id');
    }
}
