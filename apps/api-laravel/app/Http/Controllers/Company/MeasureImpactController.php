<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\Company\MeasureImpactResource;
use App\Services\Company\CompanyMeasureAccessService;
use App\Services\Insights\Contracts\MeasureImpactProvider;
use Illuminate\Http\Request;

class MeasureImpactController extends Controller
{
    public function __construct(
        private readonly MeasureImpactProvider $impactProvider,
        private readonly CompanyMeasureAccessService $measureAccessService,
    ) {
    }

    public function show(Request $request, int|string $id)
    {
        $user = $request->user();
        $measure = $this->measureAccessService->readableMeasureFor($user, $id);
        $impact = $this->impactProvider->impactFor($user, $measure);

        return response()->json([
            'data' => $impact === null ? null : (new MeasureImpactResource($impact))->resolve($request),
        ]);
    }
}
