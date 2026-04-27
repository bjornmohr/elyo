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
        if ($user->role->value === 'EMPLOYEE') {
            abort(403);
        }

        $isManager = $user->role->value === 'COMPANY_MANAGER';
        $limit = (int) $request->query('limit', 12);

        $teamId = $isManager
            ? $user->managed_team_id
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
