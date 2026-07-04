<?php

namespace App\Services\Insights\Db;

use App\Models\User;
use App\Services\AnonymityService;
use App\Services\Company\CompanyMeasureAccessService;
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
        private readonly CompanyMeasureAccessService $measureAccessService,
        private readonly MeasureParticipationSummaryService $summaryService,
    ) {
    }

    public function statisticsFor(User $user): array
    {
        $threshold = $user->company?->anonymity_threshold ?? AnonymityService::DEFAULT_THRESHOLD;

        $byField = array_fill_keys(array_keys(RiskFields::FIELDS), []);
        $measures = $this->measureAccessService->readableMeasureQueryFor($user)
            ->where('status', '!=', 'DISMISSED')
            ->get();

        foreach ($measures as $measure) {
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
}
