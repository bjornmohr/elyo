<?php

namespace App\Services\Insights\Demo;

use App\Models\Measure;
use App\Models\User;
use App\Services\Insights\Contracts\MeasureImpactProvider;
use App\Services\Insights\RiskFields;

class DemoMeasureImpactProvider implements MeasureImpactProvider
{
    /**
     * Net effect (points) to star rating bands. Base netEffect 9 -> 3 stars
     * as in the mockup.
     */
    private const RATING_BANDS = [
        [13, 5],
        [10, 4],
        [6, 3],
        [3, 2],
    ];

    public function __construct(private readonly DemoDataRepository $repository)
    {
    }

    public function impactFor(User $user, Measure $measure): ?array
    {
        if ($measure->status !== 'COMPLETED') {
            return null;
        }

        $base = $this->repository->load('measure-impact');
        $variance = new DemoVariance($user->company, 'measure-impact:'.$measure->id);

        $participantsN = max(5, $variance->count($base['participants']['n']));
        $controlN = max(5, $variance->count($base['control']['n']));

        $pBefore = $variance->score($base['participants']['scoreBefore']);
        $pDelta = (int) round($variance->trend(
            $base['participants']['scoreAfter'] - $base['participants']['scoreBefore'],
            3,
        ));
        $cBefore = $variance->score($base['control']['scoreBefore']);
        $cDelta = (int) round($variance->trend(
            $base['control']['scoreAfter'] - $base['control']['scoreBefore'],
            2,
        ));

        $pAfter = min(100, max(0, $pBefore + $pDelta));
        $cAfter = min(100, max(0, $cBefore + $cDelta));
        $netEffect = ($pAfter - $pBefore) - ($cAfter - $cBefore);

        return [
            'measureId' => $measure->id,
            'field' => RiskFields::categoryToField($measure->category) ?? $measure->category,
            'windowWeeks' => $base['windowWeeks'],
            'participants' => ['n' => $participantsN, 'scoreBefore' => $pBefore, 'scoreAfter' => $pAfter],
            'control' => ['n' => $controlN, 'scoreBefore' => $cBefore, 'scoreAfter' => $cAfter],
            'netEffect' => $netEffect,
            'rating' => $this->ratingFor($netEffect),
            'isAboveThreshold' => $base['isAboveThreshold'],
        ];
    }

    private function ratingFor(int $netEffect): int
    {
        foreach (self::RATING_BANDS as [$minimum, $rating]) {
            if ($netEffect >= $minimum) {
                return $rating;
            }
        }

        return 1;
    }
}
