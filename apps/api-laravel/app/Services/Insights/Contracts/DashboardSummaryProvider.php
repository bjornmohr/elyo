<?php

namespace App\Services\Insights\Contracts;

use App\Models\User;

interface DashboardSummaryProvider
{
    /**
     * Executive summary for the company dashboard (2a) or null when
     * unavailable.
     *
     * @return array<string, mixed>|null
     */
    public function summaryFor(User $user): ?array;
}
