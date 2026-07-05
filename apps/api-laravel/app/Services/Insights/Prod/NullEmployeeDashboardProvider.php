<?php

namespace App\Services\Insights\Prod;

use App\Models\User;
use App\Services\Insights\Contracts\EmployeeDashboardProvider;

class NullEmployeeDashboardProvider implements EmployeeDashboardProvider
{
    public function blocksFor(User $user): ?array
    {
        return null;
    }
}
