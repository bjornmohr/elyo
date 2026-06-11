<?php

namespace App\Services;

use App\Models\User;
use App\Models\WellbeingEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnonymityService
{
    public const DEFAULT_THRESHOLD = 5;

    /**
     * Get aggregated metrics for a company or team, respecting anonymity threshold.
     */
    public function getAggregatedMetrics(string $companyId, array $options = []): array
    {
        $threshold = $options['threshold'] ?? self::DEFAULT_THRESHOLD;

        $query = WellbeingEntry::where('company_id', $companyId);

        $query->whereHas('user', fn ($q) => $q->reportableForCompanyAggregates($companyId, $this->teamIds($options)));

        if (! empty($options['periodKey'])) {
            $query->where('period_key', $options['periodKey']);
        }

        if (! empty($options['fromDate'])) {
            $query->where('created_at', '>=', $options['fromDate']);
        }

        if (! empty($options['toDate'])) {
            $query->where('created_at', '<=', $options['toDate']);
        }

        $stats = $query->selectRaw('
            AVG(mood) as avg_mood,
            AVG(stress) as avg_stress,
            AVG(energy) as avg_energy,
            AVG(score) as avg_score,
            COUNT(id) as response_count,
            COUNT(DISTINCT user_id) as respondent_count
        ')->first();

        $responseCount = (int) $stats->response_count;
        $respondentCount = (int) $stats->respondent_count;
        $eligibleEmployeeCount = $this->eligibleEmployeeCount($companyId, $options);
        $participationRate = $eligibleEmployeeCount > 0
            ? min(100, round(($respondentCount / $eligibleEmployeeCount) * 100))
            : 0;

        if ($respondentCount < $threshold) {
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

        return [
            'avgMood' => round($stats->avg_mood, 1),
            'avgStress' => round($stats->avg_stress, 1),
            'avgEnergy' => round($stats->avg_energy, 1),
            'avgScore' => round($stats->avg_score, 1),
            'responseCount' => $responseCount,
            'respondentCount' => $respondentCount,
            'eligibleEmployeeCount' => $eligibleEmployeeCount,
            'participationRate' => $participationRate,
            'isAboveThreshold' => true,
            'suppressionReason' => null,
        ];
    }

    /**
     * Get trend data over time.
     */
    public function getTrendData(string $companyId, array $options = []): array
    {
        $threshold = $options['threshold'] ?? self::DEFAULT_THRESHOLD;
        $limit = $options['limit'] ?? 12;

        $query = WellbeingEntry::where('company_id', $companyId);

        $query->whereHas('user', fn ($q) => $q->reportableForCompanyAggregates($companyId, $this->teamIds($options)));

        $raw = $query->groupBy('period_key')
            ->selectRaw('
                period_key,
                AVG(score) as avg_score,
                AVG(mood) as avg_mood,
                AVG(stress) as avg_stress,
                AVG(energy) as avg_energy,
                COUNT(DISTINCT user_id) as respondent_count
            ')
            ->orderBy('period_key', 'desc')
            ->limit($limit)
            ->get();

        return $raw->filter(fn ($item) => $item->respondent_count >= $threshold)
            ->map(fn ($item) => [
                'period' => $item->period_key,
                'avgScore' => round($item->avg_score, 1),
                'avgMood' => round($item->avg_mood, 1),
                'avgStress' => round($item->avg_stress, 1),
                'avgEnergy' => round($item->avg_energy, 1),
            ])
            ->reverse()
            ->values()
            ->toArray();
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

        $currentPeriod = $this->currentPeriodKey();
        $reportableUserConstraint = fn ($query) => $query->whereHas(
            'user',
            fn ($q) => $q->reportableForCompanyAggregates($companyId, $this->teamIds($options))
        );

        $checkedInThisPeriod = WellbeingEntry::where('company_id', $companyId)
            ->where('period_key', $currentPeriod)
            ->tap($reportableUserConstraint)
            ->distinct('user_id')
            ->count('user_id');

        // Get last 4 periods present in the DB for this company
        $periodKeys = WellbeingEntry::where('company_id', $companyId)
            ->tap($reportableUserConstraint)
            ->orderBy('period_key', 'desc')
            ->distinct()
            ->limit(4)
            ->pluck('period_key');

        $continuousUsers = 0;
        if ($periodKeys->count() >= 3) {
            $continuousUsers = WellbeingEntry::where('company_id', $companyId)
                ->whereIn('period_key', $periodKeys)
                ->tap($reportableUserConstraint)
                ->groupBy('user_id')
                ->havingRaw('COUNT(DISTINCT period_key) >= 3')
                ->get()
                ->count();
        }

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
        return User::query()
            ->reportableForCompanyAggregates($companyId, $this->teamIds($options))
            ->count();
    }

    private function teamIds(array $options): ?array
    {
        return ! empty($options['teamId']) ? [$options['teamId']] : null;
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
