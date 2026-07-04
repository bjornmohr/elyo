<?php

namespace App\Services\Insights\Demo;

use App\Models\User;
use App\Services\Insights\Contracts\EmployeeDashboardProvider;

class DemoEmployeeDashboardProvider implements EmployeeDashboardProvider
{
    public function __construct(private readonly DemoDataRepository $repository)
    {
    }

    public function blocksFor(User $user): ?array
    {
        $base = $this->repository->load('employee-dashboard');
        $variance = new DemoVariance($user->company, 'employee-dashboard');

        if ($variance->isIdentity()) {
            return $base;
        }

        $sleep = $base['sleep'];
        $sleep['currentH'] = $variance->days($sleep['currentH'], 0.1);
        $sleep['previousH'] = $variance->days($sleep['previousH'], 0.1);

        $bodySignals = array_map(function (array $signal) use ($variance) {
            $signal['thisWeekDays'] = max(0, $variance->count($signal['thisWeekDays'], 0.4));
            $signal['lastWeekDays'] = max(0, $variance->count($signal['lastWeekDays'], 0.4));

            return $signal;
        }, $base['bodySignals']);

        return [
            'sleep' => $sleep,
            'healthFlag' => $base['healthFlag'],
            'bodySignals' => $bodySignals,
            'levers' => $base['levers'],
        ];
    }
}
