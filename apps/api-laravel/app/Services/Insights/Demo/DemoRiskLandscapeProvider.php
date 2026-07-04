<?php

namespace App\Services\Insights\Demo;

use App\Models\User;
use App\Services\Insights\Contracts\RiskLandscapeProvider;

class DemoRiskLandscapeProvider implements RiskLandscapeProvider
{
    public function __construct(private readonly DemoDataRepository $repository)
    {
    }

    public function landscapeFor(User $user): array
    {
        $base = $this->repository->load('risk-landscape');
        $variance = new DemoVariance($user->company, 'risk-landscape');

        $fields = array_map(function (array $row) use ($variance) {
            $row['score'] = $variance->score($row['score']);
            $row['trend30d'] = $variance->trend($row['trend30d']);
            $row['monthlyScores'] = array_map(
                fn (array $month) => [
                    'period' => $month['period'],
                    'score' => $variance->score($month['score'], 4),
                ],
                $row['monthlyScores'],
            );
            // Keep the mini trend consistent with the headline score.
            $lastIndex = count($row['monthlyScores']) - 1;
            if ($lastIndex >= 0) {
                $row['monthlyScores'][$lastIndex]['score'] = $row['score'];
            }

            return $row;
        }, $base['fields']);

        return [
            'fields' => $fields,
            'recommendations' => $base['recommendations'],
        ];
    }
}
