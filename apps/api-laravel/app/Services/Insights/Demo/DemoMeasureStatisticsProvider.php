<?php

namespace App\Services\Insights\Demo;

use App\Models\User;
use App\Services\Insights\Contracts\MeasureStatisticsProvider;

class DemoMeasureStatisticsProvider implements MeasureStatisticsProvider
{
    public function __construct(private readonly DemoDataRepository $repository)
    {
    }

    public function statisticsFor(User $user): array
    {
        $base = $this->repository->load('measure-statistics');
        $variance = new DemoVariance($user->company, 'measure-statistics');

        return array_map(function (array $row) use ($variance) {
            $row['measureCount'] = $variance->count($row['measureCount'], 0.4);
            $row['avgParticipationRate'] = $variance->percent($row['avgParticipationRate']);
            $row['avgImpactRating'] = $variance->rating($row['avgImpactRating']);
            $row['fieldTrend30d'] = $variance->trend($row['fieldTrend30d']);

            // A gap row must stay a gap row and vice versa; jitter may not
            // move a field across the "has measures" boundary implausibly.
            if ($row['measureCount'] === 0) {
                $row['avgParticipationRate'] = null;
                $row['avgImpactRating'] = null;
                $row['impactIsPreliminary'] = false;
            }

            return $row;
        }, $base['fields']);
    }
}
