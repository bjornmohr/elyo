<?php

namespace App\Services;

use App\Models\WellbeingEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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

        if (!empty($options['teamId'])) {
            $query->whereHas('user', function ($q) use ($options) {
                $q->where('team_id', $options['teamId']);
            });
        }

        if (!empty($options['periodKey'])) {
            $query->where('period_key', $options['periodKey']);
        }

        if (!empty($options['fromDate'])) {
            $query->where('created_at', '>=', $options['fromDate']);
        }

        if (!empty($options['toDate'])) {
            $query->where('created_at', '<=', $options['toDate']);
        }

        $stats = $query->selectRaw('
            AVG(mood) as avg_mood,
            AVG(stress) as avg_stress,
            AVG(energy) as avg_energy,
            AVG(score) as avg_score,
            COUNT(id) as response_count
        ')->first();

        $count = (int) $stats->response_count;

        if ($count < $threshold) {
            return [
                'avgMood' => 0,
                'avgStress' => 0,
                'avgEnergy' => 0,
                'avgScore' => 0,
                'responseCount' => $count,
                'isAboveThreshold' => false,
            ];
        }

        return [
            'avgMood' => round($stats->avg_mood, 1),
            'avgStress' => round($stats->avg_stress, 1),
            'avgEnergy' => round($stats->avg_energy, 1),
            'avgScore' => round($stats->avg_score, 1),
            'responseCount' => $count,
            'isAboveThreshold' => true,
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

        if (!empty($options['teamId'])) {
            $query->whereHas('user', function ($q) use ($options) {
                $q->where('team_id', $options['teamId']);
            });
        }

        $raw = $query->groupBy('period_key')
            ->selectRaw('
                period_key,
                AVG(score) as avg_score,
                AVG(mood) as avg_mood,
                AVG(stress) as avg_stress,
                AVG(energy) as avg_energy,
                COUNT(id) as response_count
            ')
            ->orderBy('period_key', 'desc')
            ->limit($limit)
            ->get();

        return $raw->filter(fn($item) => $item->response_count >= $threshold)
            ->map(fn($item) => [
                'period' => $item->period_key,
                'avgScore' => round($item->avg_score, 1),
                'avgMood' => round($item->avg_mood, 1),
                'avgStress' => round($item->avg_stress, 1),
                'avgEnergy' => round($item->avg_energy, 1),
                'respondents' => (int) $item->response_count,
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

        $totalEmployees = User::where('company_id', $companyId)
            ->where('role', \App\Enums\Role::EMPLOYEE)
            ->where('is_active', true)
            ->count();

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
        $checkedInThisPeriod = WellbeingEntry::where('company_id', $companyId)
            ->where('period_key', $currentPeriod)
            ->count();

        // Get last 4 periods present in the DB for this company
        $periodKeys = WellbeingEntry::where('company_id', $companyId)
            ->orderBy('period_key', 'desc')
            ->distinct()
            ->limit(4)
            ->pluck('period_key');

        $continuousUsers = 0;
        if ($periodKeys->count() >= 3) {
            $continuousUsers = WellbeingEntry::where('company_id', $companyId)
                ->whereIn('period_key', $periodKeys)
                ->groupBy('user_id')
                ->havingRaw('COUNT(DISTINCT period_key) >= 3')
                ->get()
                ->count();
        }

        return [
            'continuityRate' => $totalEmployees > 0 ? round(($continuousUsers / $totalEmployees) * 100) : 0,
            'activeUserRate' => $totalEmployees > 0 ? round(($checkedInThisPeriod / $totalEmployees) * 100) : 0,
            'totalEmployees' => $totalEmployees,
            'checkedInThisPeriod' => $checkedInThisPeriod,
            'isAboveThreshold' => true,
        ];
    }

    /**
     * Calculate current period key (YYYY-Www).
     */
    public function currentPeriodKey(): string
    {
        $now = Carbon::now();
        return $now->format('Y') . '-W' . str_pad($now->weekOfYear, 2, '0', STR_PAD_LEFT);
    }
}
