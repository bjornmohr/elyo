<?php

namespace App\Services\Insights\Prod;

use App\Models\User;
use App\Services\Insights\Contracts\DashboardSummaryProvider;

class NullDashboardSummaryProvider implements DashboardSummaryProvider
{
    public function summaryFor(User $user): ?array
    {
        return null;
    }
}
