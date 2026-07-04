<?php

namespace App\Services\Insights\Prod;

use App\Models\User;
use App\Services\Insights\Contracts\UsageFunnelProvider;

class NullUsageFunnelProvider implements UsageFunnelProvider
{
    public function funnelFor(User $user): ?array
    {
        return null;
    }
}
