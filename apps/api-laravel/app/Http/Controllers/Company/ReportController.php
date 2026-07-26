<?php

namespace App\Http\Controllers\Company;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\Company\ReportingPendingResource;
use App\Models\Team;
use App\Services\Company\TeamLayerGuard;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private readonly TeamLayerGuard $teamLayerGuard) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('roles');

        $isManager = $user->hasRole('COMPANY_MANAGER') && ! $user->hasAnyRole([Role::COMPANY_ADMIN, Role::COMPANY_OWNER]);
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

        // ELYO-91 prompt 09: the trend source moved into the health domain and
        // the company runtime must not aggregate it live (ADR-003 D7). Scope and
        // authorization checks above stay in force so the endpoint keeps its
        // contract; only the payload is the pending block.
        return response()->json(new ReportingPendingResource);
    }
}
