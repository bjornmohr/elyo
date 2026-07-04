<?php

namespace App\Services\Insights\Prod;

use App\Models\User;
use App\Services\Insights\Contracts\RiskLandscapeProvider;

class NullRiskLandscapeProvider implements RiskLandscapeProvider
{
    public function landscapeFor(User $user): array
    {
        return [];
    }
}
