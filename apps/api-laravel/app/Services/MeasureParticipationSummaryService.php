<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Measure;
use App\Models\MeasureParticipation;
use App\Models\Team;
use App\Models\User;
use App\Services\Company\ReportingPercentage;
use App\Services\Company\TeamLayerGuard;
use Illuminate\Database\Eloquent\Builder;

class MeasureParticipationSummaryService
{
    public function __construct(private readonly TeamLayerGuard $teamLayerGuard)
    {
    }

    public function summaryFor(User $user, int|string $measureId, int $threshold): array
    {
        $user->loadMissing('roles');
        $isManager = $this->teamLayerGuard->isManagerOnly($user);
        $teamLayerEnabled = $this->teamLayerGuard->enabledFor($user);

        if (! $teamLayerEnabled && $isManager) {
            $this->teamLayerGuard->abortIfDisabled($user);
        }

        $measure = Measure::query()
            ->whereKey($measureId)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        if (! $teamLayerEnabled && $measure->team_id !== null) {
            $this->teamLayerGuard->abortIfDisabled($user);
        }

        $scopeTeamIds = $this->scopeTeamIds($user, $measure, $isManager);
        $eligibleEmployeesQuery = $this->eligibleEmployeesQuery($user, $scopeTeamIds);
        $eligibleCount = (clone $eligibleEmployeesQuery)->count();
        $participantCount = $this->participantCount($measure, $eligibleEmployeesQuery);
        $isAboveThreshold = $eligibleCount >= $threshold && $participantCount >= $threshold;

        if (! $isAboveThreshold) {
            return [
                'measureId' => $measure->id,
                'isAboveThreshold' => false,
                'eligibleCount' => null,
                'participantCount' => null,
                'participationRate' => null,
                'suppressionReason' => 'ANONYMITY_THRESHOLD_NOT_MET',
                'teamBreakdown' => null,
            ];
        }

        return [
            'measureId' => $measure->id,
            'isAboveThreshold' => true,
            'eligibleCount' => $eligibleCount,
            'participantCount' => $participantCount,
            'participationRate' => ReportingPercentage::of($participantCount, $eligibleCount),
            'suppressionReason' => null,
            'teamBreakdown' => null,
        ];
    }

    private function scopeTeamIds(User $user, Measure $measure, bool $isManager): ?array
    {
        if (! $isManager) {
            return $measure->team_id === null ? null : [(int) $measure->team_id];
        }

        $managedTeamId = Team::query()
            ->where('manager_id', $user->id)
            ->where('company_id', $user->company_id)
            ->value('id');

        abort_if(! $managedTeamId, 403);

        if ($measure->team_id !== null && (int) $measure->team_id !== (int) $managedTeamId) {
            abort(403);
        }

        return [(int) $managedTeamId];
    }

    private function eligibleEmployeesQuery(User $user, ?array $scopeTeamIds): Builder
    {
        return User::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->whereHas('roles', fn (Builder $query) => $query->where('role', Role::EMPLOYEE->value))
            ->when($scopeTeamIds !== null, fn (Builder $query) => $query->whereIn('team_id', $scopeTeamIds));
    }

    private function participantCount(Measure $measure, Builder $eligibleEmployeesQuery): int
    {
        return MeasureParticipation::query()
            ->where('measure_id', $measure->id)
            ->where('company_id', $measure->company_id)
            ->whereIn('user_id', $eligibleEmployeesQuery->select('users.id'))
            ->distinct('user_id')
            ->count('user_id');
    }
}
