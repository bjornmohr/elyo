<?php

namespace App\Services\Insights\Db;

use App\Enums\Role;
use App\Models\Measure;
use App\Models\Team;
use App\Models\User;
use App\Services\AnonymityService;
use App\Services\Company\TeamLayerGuard;
use App\Services\Insights\Contracts\MeasureStatisticsProvider;
use App\Services\Insights\RiskFields;
use App\Services\MeasureParticipationSummaryService;

/**
 * Real aggregation for the statistics page: measure counts and average
 * participation per risk field are computable today; impact rating and
 * field trend stay null until the extended check-in ships.
 */
class DbMeasureStatisticsProvider implements MeasureStatisticsProvider
{
    public function __construct(
        private readonly TeamLayerGuard $teamLayerGuard,
        private readonly MeasureParticipationSummaryService $summaryService,
    ) {
    }

    public function statisticsFor(User $user): array
    {
        $threshold = $user->company?->anonymity_threshold ?? AnonymityService::DEFAULT_THRESHOLD;

        $byField = array_fill_keys(array_keys(RiskFields::FIELDS), []);
        foreach ($this->scopedMeasures($user) as $measure) {
            $field = RiskFields::categoryToField($measure->category);
            if ($field !== null) {
                $byField[$field][] = $measure;
            }
        }

        $rows = [];
        foreach (RiskFields::FIELDS as $field => $definition) {
            $measures = $byField[$field];
            $rates = [];
            foreach ($measures as $measure) {
                $summary = $this->summaryService->summaryFor($user, $measure->id, $threshold);
                if ($summary['participationRate'] !== null) {
                    $rates[] = $summary['participationRate'];
                }
            }

            $rows[] = [
                'field' => $field,
                'fieldLabel' => $definition['label'],
                'measureCount' => count($measures),
                'avgParticipationRate' => $rates !== [] ? round(array_sum($rates) / count($rates), 1) : null,
                // Only false when the field has measures but every summary
                // was suppressed by the anonymity threshold.
                'isAboveThreshold' => $measures === [] || $rates !== [],
                'avgImpactRating' => null,
                'impactIsPreliminary' => false,
                'fieldTrend30d' => null,
            ];
        }

        return $rows;
    }

    /**
     * Same visibility rules as MeasureController::index, minus dismissed
     * measures (the statistics view counts suggested + active + completed).
     *
     * @return \Illuminate\Support\Collection<int, Measure>
     */
    private function scopedMeasures(User $user)
    {
        $user->loadMissing('roles');
        $isManager = $user->hasRole('COMPANY_MANAGER') && ! $user->hasAnyRole([Role::COMPANY_ADMIN, Role::COMPANY_OWNER]);
        $teamLayerEnabled = $this->teamLayerGuard->enabledFor($user);

        if (! $teamLayerEnabled && $isManager) {
            $this->teamLayerGuard->abortIfDisabled($user);
        }

        $query = Measure::where('company_id', $user->company_id)
            ->where('status', '!=', 'DISMISSED');

        if (! $teamLayerEnabled) {
            $query->whereNull('team_id');
        }

        if ($isManager) {
            $managedTeamId = Team::where('manager_id', $user->id)
                ->where('company_id', $user->company_id)
                ->value('id');
            if (! $managedTeamId) {
                return collect();
            }
            $query->where(function ($q) use ($managedTeamId) {
                $q->whereNull('team_id')->orWhere('team_id', $managedTeamId);
            });
        }

        return $query->get();
    }
}
