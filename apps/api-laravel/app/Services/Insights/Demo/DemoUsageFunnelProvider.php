<?php

namespace App\Services\Insights\Demo;

use App\Models\User;
use App\Services\Insights\Contracts\UsageFunnelProvider;

class DemoUsageFunnelProvider implements UsageFunnelProvider
{
    public function __construct(private readonly DemoDataRepository $repository)
    {
    }

    public function funnelFor(User $user): ?array
    {
        $base = $this->repository->load('usage-funnel');
        $variance = new DemoVariance($user->company, 'usage-funnel');

        if ($variance->isIdentity()) {
            return $base;
        }

        // Jitter the top of the funnel and the per-stage retention ratios,
        // then recompute counts top-down so stages stay monotone.
        $stages = $base['stages'];
        $registered = max(10, $variance->count($stages[0]['count'], 0.2));
        $previousBaseCount = $stages[0]['count'];
        $previousCount = $registered;

        $stages[0]['count'] = $registered;
        $stages[0]['rate'] = 100;

        for ($i = 1, $n = count($stages); $i < $n; $i++) {
            $baseRetention = $previousBaseCount > 0 ? $stages[$i]['count'] / $previousBaseCount : 0;
            $retention = min(1.0, max(0.05, ($variance->percent($baseRetention * 100, 6) ?? 0) / 100));

            $previousBaseCount = $stages[$i]['count'];
            $previousCount = (int) round($previousCount * $retention);

            $stages[$i]['count'] = $previousCount;
            $stages[$i]['rate'] = (int) round($previousCount / $registered * 100);
        }

        $transitions = array_map(function (array $transition) use ($variance) {
            // The final 14-day window is fixed by definition, not measured.
            if ($transition['to'] !== 'active_14d') {
                $transition['avgDays'] = $variance->days($transition['avgDays']);
            }

            return $transition;
        }, $base['transitions']);

        $previousCohort = $base['previousCohort'];
        if ($previousCohort !== null) {
            $previousCohort['stages'] = array_map(function (array $stage) use ($variance) {
                if ($stage['key'] !== 'registered') {
                    $stage['rate'] = $variance->percent($stage['rate'], 6);
                }

                return $stage;
            }, $previousCohort['stages']);
        }

        return [
            'cohort' => $base['cohort'],
            'stages' => $stages,
            'transitions' => $transitions,
            'returnRate14d' => $variance->percent($base['returnRate14d']),
            'avgDaysToFirstDecision' => $variance->days($base['avgDaysToFirstDecision']),
            'previousCohort' => $previousCohort,
            'categoryInsight' => $base['categoryInsight'],
            'categories' => $this->variedCategories($base['categories'] ?? [], $variance),
        ];
    }

    /**
     * Jitter the per-category counts while keeping each category funnel
     * monotone (recommendation >= started >= active).
     *
     * @param  array<int, array<string, mixed>>  $categories
     * @return array<int, array<string, mixed>>
     */
    private function variedCategories(array $categories, DemoVariance $variance): array
    {
        return array_map(function (array $category) use ($variance) {
            $recommendation = max(1, $variance->count($category['recommendationReceived'], 0.2));
            $started = min($recommendation, max(0, $variance->count($category['measureStarted'], 0.2)));
            $active = min($started, max(0, $variance->count($category['active14d'], 0.25)));

            return [
                'key' => $category['key'],
                'label' => $category['label'],
                'recommendationReceived' => $recommendation,
                'measureStarted' => $started,
                'active14d' => $active,
            ];
        }, $categories);
    }
}
