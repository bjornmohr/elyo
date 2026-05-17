<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CreateTeamRequest;
use App\Http\Resources\Company\TeamResource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('roles');
        $isManager = $user->hasRole('COMPANY_MANAGER') && !$user->hasAnyRole([\App\Enums\Role::COMPANY_ADMIN, \App\Enums\Role::COMPANY_OWNER]);

        $query = Team::where('company_id', $user->company_id);

        if ($isManager) {
            $query->where('manager_id', $user->id);
        }

        $teams = $query->withCount('members')
            ->with('manager:id,name')
            ->orderBy('name', 'asc')
            ->get();

        return TeamResource::collection($teams);
    }

    public function store(CreateTeamRequest $request)
    {
        $validated = $request->validated();

        $team = Team::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? null,
            'manager_id' => $validated['managerId'] ?? null,
            'company_id' => $request->user()->company_id
        ]);

        return new TeamResource($team->loadCount('members')->load('manager:id,name'));
    }

    public function show(Request $request, $id)
    {
        $team = Team::where('id', $id)
            ->where('company_id', $request->user()->company_id)
            ->firstOrFail();

        $request->user()->loadMissing('roles');
        if ($request->user()->hasRole('COMPANY_MANAGER') && !$request->user()->hasAnyRole([\App\Enums\Role::COMPANY_ADMIN, \App\Enums\Role::COMPANY_OWNER]) && $team->manager_id !== $request->user()->id) {
            abort(403);
        }

        return new TeamResource($team->loadCount('members')->load('manager:id,name'));
    }

    public function update(CreateTeamRequest $request, $id)
    {
        $team = Team::where('id', $id)
            ->where('company_id', $request->user()->company_id)
            ->firstOrFail();

        $validated = $request->validated();
        $team->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? null,
            'manager_id' => $validated['managerId'] ?? null,
        ]);

        return new TeamResource($team->loadCount('members')->load('manager:id,name'));
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->loadMissing('roles');
        if (!$request->user()->hasAnyRole([\App\Enums\Role::COMPANY_ADMIN, \App\Enums\Role::COMPANY_OWNER])) {
            abort(403);
        }

        $team = Team::where('id', $id)
            ->where('company_id', $request->user()->company_id)
            ->firstOrFail();

        $team->delete();

        return response()->json(['ok' => true]);
    }

    public function members(Request $request, $teamId)
    {
        $user = $request->user();
        $user->loadMissing('roles');
        // Route middleware already restricts to company roles, but double-check

        $team = Team::where('id', $teamId)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        // TODO: team membership needs a proper join table or team_id on users
        $members = collect([]);

        return response()->json(['members' => $members]);
    }
}
