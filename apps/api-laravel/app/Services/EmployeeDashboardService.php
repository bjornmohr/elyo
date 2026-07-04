<?php

namespace App\Services;

use App\Models\User;
use App\Models\WellbeingEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Week-over-week aggregates for the employee dashboard (1a), computed from
 * daily wellbeing entries (period_key = Y-m-d) on the canonical 1-5 scale.
 */
class EmployeeDashboardService
{
    /**
     * @return array{wellbeing: array<string, mixed>, metrics: array<string, mixed>}|null
     */
    public function weeklyAggregates(User $user): ?array
    {
        $since = Carbon::now()->startOfWeek()->subWeek();

        $entries = WellbeingEntry::query()
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            // Daily keys only — weekly aggregate rows (2026-W27) are ignored.
            ->where('period_key', 'like', '____-__-__')
            ->where('period_key', '>=', $since->toDateString())
            ->orderBy('period_key')
            ->get();

        if ($entries->isEmpty()) {
            return null;
        }

        $weekStart = Carbon::now()->startOfWeek()->toDateString();
        $currentWeek = $entries->filter(fn (WellbeingEntry $entry) => $entry->period_key >= $weekStart);
        $previousWeek = $entries->filter(fn (WellbeingEntry $entry) => $entry->period_key < $weekStart);

        $current = $this->averages($currentWeek);
        $previous = $this->averages($previousWeek);

        $sparkline = $entries->slice(-14)->map(
            fn (WellbeingEntry $entry) => round((float) $entry->score, 1)
        )->values()->all();

        return [
            'wellbeing' => [
                'current' => $current['score'],
                'previous' => $previous['score'],
                'delta' => $this->delta($current['score'], $previous['score']),
                'scale' => 5,
                'sparkline' => $sparkline,
            ],
            'metrics' => [
                'mood' => $this->metric($current['mood'], $previous['mood']),
                'energy' => $this->metric($current['energy'], $previous['energy']),
                'stress' => $this->metric($current['stress'], $previous['stress']),
            ],
        ];
    }

    /**
     * @param  Collection<int, WellbeingEntry>  $entries
     * @return array{mood: float|null, energy: float|null, stress: float|null, score: float|null}
     */
    private function averages(Collection $entries): array
    {
        if ($entries->isEmpty()) {
            return ['mood' => null, 'energy' => null, 'stress' => null, 'score' => null];
        }

        $mood = round($entries->avg('mood'), 1);
        $energy = round($entries->avg('energy'), 1);
        $stress = round($entries->avg('stress'), 1);

        return [
            'mood' => $mood,
            'energy' => $energy,
            'stress' => $stress,
            // Derived demo value: plain mean via the canonical score formula.
            'score' => round(($mood + (6 - $stress) + $energy) / 3, 1),
        ];
    }

    private function metric(?float $current, ?float $previous): array
    {
        return [
            'current' => $current,
            'previous' => $previous,
            'delta' => $this->delta($current, $previous),
        ];
    }

    private function delta(?float $current, ?float $previous): ?float
    {
        if ($current === null || $previous === null) {
            return null;
        }

        return round($current - $previous, 1);
    }
}
