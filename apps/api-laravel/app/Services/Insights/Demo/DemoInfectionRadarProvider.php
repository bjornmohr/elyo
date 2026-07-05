<?php

namespace App\Services\Insights\Demo;

use App\Models\User;
use App\Services\Insights\Contracts\InfectionRadarProvider;
use Illuminate\Support\Carbon;

class DemoInfectionRadarProvider implements InfectionRadarProvider
{
    public function __construct(private readonly DemoDataRepository $repository)
    {
    }

    public function radarFor(User $user): ?array
    {
        $base = $this->repository->load('infection-radar');
        $variance = new DemoVariance($user->company, 'infection-radar');

        $counts = array_map(fn (int $count) => $variance->count($count, 0.3), $base['symptomReports7d']);

        $reports = [];
        $start = Carbon::today()->subDays(6);
        foreach ($counts as $index => $count) {
            $reports[] = [
                'date' => $start->copy()->addDays($index)->toDateString(),
                'count' => $count,
            ];
        }

        $internalSignals = $base['internalSignals'];
        $internalSignals['safeModeActivations7d'] = $variance->count($internalSignals['safeModeActivations7d'], 0.25);

        return [
            'overallStatus' => $base['overallStatus'],
            'earlyWarning' => $base['earlyWarning'],
            'internalSignals' => $internalSignals,
            'externalSignals' => $base['externalSignals'],
            'locations' => $base['locations'],
            'symptomReports7d' => $reports,
            'rkiIncidence' => [
                'value' => $variance->count($base['rkiIncidence']['value'], 0.2),
                'deltaPercent' => $variance->trend($base['rkiIncidence']['deltaPercent'], 5),
                'district' => $base['rkiIncidence']['district'],
            ],
            'recommendations' => $base['recommendations'],
        ];
    }
}
