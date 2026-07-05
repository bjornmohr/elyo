<?php

namespace App\Services\Insights\Demo;

use App\Models\User;
use App\Services\Insights\Contracts\DashboardSummaryProvider;

class DemoDashboardSummaryProvider implements DashboardSummaryProvider
{
    public function __construct(private readonly DemoDataRepository $repository)
    {
    }

    public function summaryFor(User $user): ?array
    {
        $base = $this->repository->load('dashboard-summary');
        $variance = new DemoVariance($user->company, 'dashboard-summary');

        if ($variance->isIdentity()) {
            return $base;
        }

        $kpis = $base['kpis'];
        $kpis['healthIndex']['value'] = $variance->score($kpis['healthIndex']['value']);
        $kpis['healthIndex']['deltaPercent'] = $variance->trend($kpis['healthIndex']['deltaPercent']);
        $kpis['riskTrend30d']['value'] = $variance->trend($kpis['riskTrend30d']['value']);
        $kpis['activeUserRate']['value'] = $variance->percent($kpis['activeUserRate']['value']);
        $kpis['activeUserRate']['deltaPercent'] = $variance->trend($kpis['activeUserRate']['deltaPercent']);
        $kpis['measureImpactScore']['value'] = $variance->rating($kpis['measureImpactScore']['value']);

        $riskCompact = array_map(function (array $row) use ($variance) {
            $row['trend30d'] = $variance->trend($row['trend30d']);

            return $row;
        }, $base['riskCompact']);

        $funnelCompact = $base['funnelCompact'];
        $funnelCompact['rates'] = array_map(
            fn (int $rate, int $index) => $index === 0 ? 100 : ($variance->percent($rate, 6) ?? 0),
            $funnelCompact['rates'],
            array_keys($funnelCompact['rates']),
        );
        // Rates further down the funnel may never exceed earlier stages.
        for ($i = 1, $n = count($funnelCompact['rates']); $i < $n; $i++) {
            $funnelCompact['rates'][$i] = min($funnelCompact['rates'][$i], $funnelCompact['rates'][$i - 1]);
        }
        $funnelCompact['avgDaysToFirstDecision'] = $variance->days($funnelCompact['avgDaysToFirstDecision']);
        $funnelCompact['returnRate14d'] = $variance->percent($funnelCompact['returnRate14d']);

        $impactReporting = array_map(function (array $row) use ($variance) {
            $row['usageRate'] = $variance->percent($row['usageRate']);
            $row['relevanceScore'] = $variance->score($row['relevanceScore']);
            $row['rating'] = $variance->rating($row['rating']);

            return $row;
        }, $base['impactReporting']);

        $infectionWidget = $base['infectionWidget'];
        $infectionWidget['rkiIncidence'] = $variance->count($infectionWidget['rkiIncidence'], 0.2);
        $infectionWidget['rkiDeltaPercent'] = $variance->trend($infectionWidget['rkiDeltaPercent'], 5);
        $infectionWidget['internalDeltaPp'] = $variance->trend($infectionWidget['internalDeltaPp'], 2);
        $infectionWidget['safeModeActivations7d'] = $variance->count($infectionWidget['safeModeActivations7d'], 0.25);

        return [
            'period' => $base['period'],
            'kpis' => $kpis,
            'riskCompact' => $riskCompact,
            'funnelCompact' => $funnelCompact,
            'infectionWidget' => $infectionWidget,
            'impactReporting' => $impactReporting,
            'recommendations' => $base['recommendations'],
        ];
    }
}
