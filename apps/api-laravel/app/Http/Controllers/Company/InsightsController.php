<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Services\Insights\Contracts\InfectionRadarProvider;
use App\Services\Insights\Contracts\RiskLandscapeProvider;
use App\Services\Insights\Contracts\UsageFunnelProvider;
use Illuminate\Http\Request;

class InsightsController extends Controller
{
    public function __construct(
        private readonly RiskLandscapeProvider $riskLandscapeProvider,
        private readonly UsageFunnelProvider $usageFunnelProvider,
        private readonly InfectionRadarProvider $infectionRadarProvider,
    ) {
    }

    public function riskLandscape(Request $request)
    {
        return response()->json([
            'data' => $this->riskLandscapeProvider->landscapeFor($request->user()),
        ]);
    }

    public function usageFunnel(Request $request)
    {
        return response()->json([
            'data' => $this->usageFunnelProvider->funnelFor($request->user()),
        ]);
    }

    public function infectionRadar(Request $request)
    {
        return response()->json([
            'data' => $this->infectionRadarProvider->radarFor($request->user()),
        ]);
    }
}
