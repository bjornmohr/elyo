<?php

namespace App\Services\Insights\Contracts;

use App\Models\User;

interface RiskLandscapeProvider
{
    /**
     * Risk landscape rows (RiskField[] contract). Empty array when the
     * module has no data source (prod mode).
     *
     * @return array<int, array<string, mixed>>
     */
    public function landscapeFor(User $user): array;
}
