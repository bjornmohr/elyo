<?php

namespace App\Services\Insights\Contracts;

use App\Models\User;

interface InfectionRadarProvider
{
    /**
     * Infection radar (InfectionRadar contract) or null when unavailable.
     *
     * @return array<string, mixed>|null
     */
    public function radarFor(User $user): ?array;
}
