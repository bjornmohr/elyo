<?php

namespace App\Services\Insights\Contracts;

use App\Models\User;

interface EmployeeDashboardProvider
{
    /**
     * Mock-only blocks of the employee dashboard (1a): sleep, bodySignals,
     * healthFlag, levers. Null when unavailable (prod mode) — the real
     * wellbeing aggregates are computed elsewhere and are always present.
     *
     * @return array<string, mixed>|null
     */
    public function blocksFor(User $user): ?array;
}
