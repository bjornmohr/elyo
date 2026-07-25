<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnonymityService
{
    public const DEFAULT_THRESHOLD = 5;

    // ELYO-91 prompt 09: the wellbeing source of these aggregates moved into the
    // health domain, keyed on health_subject_id (ADR-003 D3). Live aggregation
    // from the company runtime was never ADR-001 §2.5 conform and the company
    // runtime has no connect privilege on the health database, so there is no
    // source to read here until the reporting domain exists (ADR-003 D7).
    //
    // Until then every wellbeing aggregate reports "no data": identical to the
    // existing empty-source behaviour, so no company-visible shape changes and
    // no new company behaviour is invented in this task. Prompt 09 replaces this
    // with the explicit reporting-pending contract state.

    /**
     * Get aggregated metrics for a company or team, respecting anonymity threshold.
     */
    public function getAggregatedMetrics(string $companyId, array $options = []): array
    {
        // ELYO-91 prompt 09: no wellbeing source (see class-level note). Zero
        // respondents can never meet an anonymity threshold, so this is the
        // unchanged suppressed block the endpoint already returns for a company
        // without check-ins.
        return [
            'avgMood' => null,
            'avgStress' => null,
            'avgEnergy' => null,
            'avgScore' => null,
            'responseCount' => null,
            'respondentCount' => null,
            'eligibleEmployeeCount' => null,
            'participationRate' => null,
            'isAboveThreshold' => false,
            'suppressionReason' => 'ANONYMITY_THRESHOLD_NOT_MET',
        ];
    }

    /**
     * Get trend data over time.
     */
    public function getTrendData(string $companyId, array $options = []): array
    {
        // ELYO-91 prompt 09: no wellbeing source (see class-level note). An empty
        // source yields no trend points that clear the threshold.
        return [];
    }

    /**
     * Get continuity and participation data.
     */
    public function getContinuityData(string $companyId, array $options = []): array
    {
        $threshold = $options['threshold'] ?? self::DEFAULT_THRESHOLD;

        $totalEmployees = $this->eligibleEmployeeCount($companyId, $options);

        if ($totalEmployees < $threshold) {
            return [
                'continuityRate' => 0,
                'activeUserRate' => 0,
                'totalEmployees' => $totalEmployees,
                'checkedInThisPeriod' => 0,
                'isAboveThreshold' => false,
            ];
        }

        // ELYO-91 prompt 09: no wellbeing source (see class-level note). With an
        // empty source nobody has checked in and nobody is continuous, which is
        // what the endpoint already reported for a company without check-ins.
        // `totalEmployees` stays identity-side and is unaffected.
        $checkedInThisPeriod = 0;
        $continuousUsers = 0;

        return [
            'continuityRate' => $totalEmployees > 0 ? round(($continuousUsers / $totalEmployees) * 100) : 0,
            'activeUserRate' => $totalEmployees > 0 ? min(100, round(($checkedInThisPeriod / $totalEmployees) * 100)) : 0,
            'totalEmployees' => $totalEmployees,
            'checkedInThisPeriod' => $checkedInThisPeriod,
            'isAboveThreshold' => true,
        ];
    }

    private function eligibleEmployeeCount(string $companyId, array $options = []): int
    {
        return User::where('company_id', $companyId)
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('role', Role::EMPLOYEE->value))
            ->when(! empty($options['teamId']), fn ($query) => $query->where('team_id', $options['teamId']))
            ->count();
    }

    /**
     * Calculate current period key (YYYY-Www).
     */
    public function currentPeriodKey(): string
    {
        $now = Carbon::now();

        return $now->format('Y').'-W'.str_pad($now->weekOfYear, 2, '0', STR_PAD_LEFT);
    }
}
