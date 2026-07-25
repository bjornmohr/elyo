<?php

namespace App\Http\Controllers\Company;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\Company\ReportingPendingResource;
use App\Http\Resources\Company\TeamResource;
use App\Models\Team;
use App\Services\Company\TeamLayerGuard;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function __construct(private readonly TeamLayerGuard $teamLayerGuard) {}

    public function dashboard(Request $request)
    {
        $user = $request->user();
        $company = $user->company;
        $companyId = $company->id;
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

        $teams = collect();
        if ($teamLayerEnabled) {
            $teamQuery = Team::where('company_id', $companyId);
            if ($isManager) {
                $teamQuery->where('id', $managedTeamId);
            }
            $teams = $teamQuery->get();
        }

        foreach ($teams as $team) {
            $team->metrics = new ReportingPendingResource;
        }

        return response()->json([
            // ELYO-91 prompt 09: no live wellbeing aggregation from the company
            // runtime (ADR-003 D7). `responseCount`/`isAboveThreshold` stay
            // present but empty for the existing Angular dashboard.
            'company' => new ReportingPendingResource([
                'isAboveThreshold' => null,
                'responseCount' => null,
            ]),
            'trend' => new ReportingPendingResource,
            'teams' => TeamResource::collection($teams),
        ]);
    }
}
