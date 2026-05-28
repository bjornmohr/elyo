<?php

namespace App\Http\Controllers\Company;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\Company\AggregatedMetricsResource;
use App\Http\Resources\Company\TeamResource;
use App\Http\Resources\Company\TrendPointResource;
use App\Models\Team;
use App\Services\AnonymityService;
use App\Services\Company\TeamLayerGuard;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    protected $anonymityService;

    public function __construct(AnonymityService $anonymityService, private readonly TeamLayerGuard $teamLayerGuard)
    {
        $this->anonymityService = $anonymityService;
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();
        $company = $user->company;
        $companyId = $company->id;
        $threshold = $company->anonymity_threshold ?? AnonymityService::DEFAULT_THRESHOLD;
        $teamLayerEnabled = $this->teamLayerGuard->enabledFor($user);

        $user->loadMissing('roles');
        $isManager = $user->hasRole('COMPANY_MANAGER') && ! $user->hasAnyRole([Role::COMPANY_ADMIN, Role::COMPANY_OWNER]);
        $this->teamLayerGuard->abortManagerWorkflowIfDisabled($user);

        $managedTeamId = null;
        if ($isManager) {
            $managedTeam = Team::where('manager_id', $user->id)->where('company_id', $companyId)->first();
            $managedTeamId = $managedTeam?->id;
        }

        if ($isManager && ! $managedTeamId) {
            return response()->json([
                'error' => 'Kein Team zugewiesen. Bitte wenden Sie sich an Ihren Administrator.',
            ], 403);
        }

        $metrics = $this->anonymityService->getAggregatedMetrics($companyId, [
            'teamId' => $isManager ? $managedTeamId : null,
            'threshold' => $threshold,
        ]);

        $trend = $this->anonymityService->getTrendData($companyId, [
            'teamId' => $isManager ? $managedTeamId : null,
            'threshold' => $threshold,
            'limit' => 12,
        ]);

        $teams = collect();
        if ($teamLayerEnabled) {
            $teamQuery = Team::where('company_id', $companyId);
            if ($isManager) {
                $teamQuery->where('id', $managedTeamId);
            }
            $teams = $teamQuery->get();
        }

        foreach ($teams as $team) {
            $teamMetrics = $this->anonymityService->getAggregatedMetrics($companyId, [
                'teamId' => $team->id,
                'threshold' => $threshold,
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
