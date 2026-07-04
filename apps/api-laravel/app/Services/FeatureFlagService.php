<?php

namespace App\Services;

use App\Models\Company;

class FeatureFlagService
{
    /**
     * Concept-module flags delivered to the frontend in auth payloads.
     * Derived from the data mode: demo => all enabled, prod => all disabled.
     * The company parameter is reserved for future per-company overrides.
     *
     * @return array<string, bool>
     */
    public function flagsForCompany(?Company $company): array
    {
        $enabled = config('elyo.data_mode') === 'demo';

        return [
            'measureImpactEnabled' => $enabled,
            'riskLandscapeEnabled' => $enabled,
            'usageFunnelEnabled' => $enabled,
            'infectionRadarEnabled' => $enabled,
        ];
    }
}
