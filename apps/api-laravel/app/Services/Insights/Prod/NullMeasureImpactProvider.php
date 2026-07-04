<?php

namespace App\Services\Insights\Prod;

use App\Models\Measure;
use App\Models\User;
use App\Services\Insights\Contracts\MeasureImpactProvider;

/**
 * Prod placeholder until the extended check-in provides field scores.
 */
class NullMeasureImpactProvider implements MeasureImpactProvider
{
    public function impactFor(User $user, Measure $measure): ?array
    {
        return null;
    }
}
