<?php

namespace App\Services\Insights\Contracts;

use App\Models\User;

interface MeasureStatisticsProvider
{
    /**
     * Per-risk-field measure statistics (MeasureFieldStatistics[] contract).
     *
     * @return array<int, array<string, mixed>>
     */
    public function statisticsFor(User $user): array;
}
