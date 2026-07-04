<?php

namespace App\Services\Insights\Contracts;

use App\Models\User;

interface UsageFunnelProvider
{
    /**
     * Usage funnel (UsageFunnel contract) or null when unavailable.
     *
     * @return array<string, mixed>|null
     */
    public function funnelFor(User $user): ?array;
}
