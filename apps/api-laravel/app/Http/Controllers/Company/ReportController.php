<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\Company\TrendPointResource;
use App\Services\AnonymityService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    protected $anonymityService;

    public function __construct(AnonymityService $anonymityService)
    {
        $this->anonymityService = $anonymityService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('roles');

        $isManager = $user->hasRole('COMPANY_MANAGER') && !$user->hasAnyRole([\App\Enums\Role::COMPANY_ADMIN, \App\Enums\Role::COMPANY_OWNER]);
        $limit = (int) $request->query('limit', 12);

        $managedTeam = $isManager ? \App\Models\Team::where('manager_id', $user->id)->where('company_id', $user->company_id)->first() : null;
        $teamId = $isManager
            ? $managedTeam?->id
            : $request->query('teamId');

        $company = $user->company;
        $threshold = $company->anonymity_threshold ?? AnonymityService::DEFAULT_THRESHOLD;

        $trend = $this->anonymityService->getTrendData($user->company_id, [
            'limit' => $limit,
            'teamId' => $teamId,
            'threshold' => $threshold
        ]);

        return TrendPointResource::collection($trend);
    }
}
