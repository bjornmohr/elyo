<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\Company\AggregatedMetricsResource;
use App\Http\Resources\Company\TrendPointResource;
use App\Http\Resources\Company\TeamResource;
use App\Services\AnonymityService;
use App\Models\Team;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    protected $anonymityService;

    public function __construct(AnonymityService $anonymityService)
    {
        $this->anonymityService = $anonymityService;
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();
        $company = $user->company;
        $companyId = $company->id;
        $threshold = $company->anonymity_threshold ?? AnonymityService::DEFAULT_THRESHOLD;

        $user->loadMissing('roles');
        $isManager = $user->hasRole('COMPANY_MANAGER') && !$user->hasAnyRole([\App\Enums\Role::COMPANY_ADMIN, \App\Enums\Role::COMPANY_OWNER]);
        $managedTeamId = null;
        if ($isManager) {
             $managedTeam = Team::where('manager_id', $user->id)->where('company_id', $companyId)->first();
             $managedTeamId = $managedTeam?->id;
        }

        if ($isManager && !$managedTeamId) {
            return response()->json([
                'error' => 'Kein Team zugewiesen. Bitte wenden Sie sich an Ihren Administrator.'
            ], 403);
        }

        $metrics = $this->anonymityService->getAggregatedMetrics($companyId, [
            'teamId' => $isManager ? $managedTeamId : null,
            'threshold' => $threshold
        ]);

        $trend = $this->anonymityService->getTrendData($companyId, [
            'teamId' => $isManager ? $managedTeamId : null,
            'threshold' => $threshold,
            'limit' => 12
        ]);

        $teamQuery = Team::where('company_id', $companyId);
        if ($isManager) {
            $teamQuery->where('id', $managedTeamId);
        }
        $teams = $teamQuery->withCount('members')->get();

        foreach ($teams as $team) {
            $teamMetrics = $this->anonymityService->getAggregatedMetrics($companyId, [
                'teamId' => $team->id,
                'threshold' => $threshold
            ]);
            $team->metrics = $teamMetrics;
        }

        return response()->json([
            'company' => new AggregatedMetricsResource($metrics),
            'trend' => TrendPointResource::collection($trend),
            'teams' => TeamResource::collection($teams),
        ]);
    }
}
