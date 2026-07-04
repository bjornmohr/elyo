<?php

namespace App\Services\Insights\Contracts;

use App\Models\Measure;
use App\Models\User;

interface MeasureImpactProvider
{
    /**
     * Impact analysis for a single measure (MeasureImpact contract),
     * or null when no impact data is available.
     *
     * @return array<string, mixed>|null
     */
    public function impactFor(User $user, Measure $measure): ?array;
}
