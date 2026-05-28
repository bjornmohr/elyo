<?php

namespace App\Http\Controllers\Company;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\Company\TrendPointResource;
use App\Models\Team;
use App\Services\AnonymityService;
use App\Services\Company\TeamLayerGuard;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    protected $anonymityService;

    public function __construct(AnonymityService $anonymityService, private readonly TeamLayerGuard $teamLayerGuard)
    {
        $this->anonymityService = $anonymityService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('roles');

        $isManager = $user->hasRole('COMPANY_MANAGER') && ! $user->hasAnyRole([Role::COMPANY_ADMIN, Role::COMPANY_OWNER]);
        $limit = (int) $request->query('limit', 12);
        $teamLayerEnabled = $this->teamLayerGuard->enabledFor($user);

        if (! $teamLayerEnabled && ($isManager || $request->query('teamId') !== null)) {
            $this->teamLayerGuard->abortIfDisabled($user);
        }

        $managedTeam = $isManager ? Team::where('manager_id', $user->id)->where('company_id', $user->company_id)->first() : null;
        $teamId = $isManager
            ? $managedTeam?->id
            : $request->query('teamId');

        if (! $isManager && $teamId !== null && ! Team::where('id', $teamId)->where('company_id', $user->company_id)->exists()) {
            abort(403);
        }

        $company = $user->company;
        $threshold = $company->anonymity_threshold ?? AnonymityService::DEFAULT_THRESHOLD;

        $trend = $this->anonymityService->getTrendData($user->company_id, [
            'limit' => $limit,
            'teamId' => $teamId,
            'threshold' => $threshold,
        ]);

        return TrendPointResource::collection($trend);
    }
}
