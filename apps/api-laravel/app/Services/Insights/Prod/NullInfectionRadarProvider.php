<?php

namespace App\Services\Insights\Prod;

use App\Models\User;
use App\Services\Insights\Contracts\InfectionRadarProvider;

class NullInfectionRadarProvider implements InfectionRadarProvider
{
    public function radarFor(User $user): ?array
    {
        return null;
    }
}
