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
        $isManager = $user->role->value === 'COMPANY_MANAGER';

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
        $team = Team::create($request->validated() + [
            'company_id' => $request->user()->company_id
        ]);

        return new TeamResource($team);
    }

    public function show(Request $request, $id)
    {
        $team = Team::where('id', $id)
            ->where('company_id', $request->user()->company_id)
            ->firstOrFail();

        if ($request->user()->role->value === 'COMPANY_MANAGER' && $team->manager_id !== $request->user()->id) {
            abort(403);
        }

        return new TeamResource($team->loadCount('members')->load('manager:id,name'));
    }

    public function update(CreateTeamRequest $request, $id)
    {
        $team = Team::where('id', $id)
            ->where('company_id', $request->user()->company_id)
            ->firstOrFail();

        $team->update($request->validated());

        return new TeamResource($team);
    }

    public function destroy(Request $request, $id)
    {
        if ($request->user()->role->value !== 'COMPANY_ADMIN') {
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
        if ($user->role->value === 'EMPLOYEE') {
            abort(403);
        }

        $team = Team::where('id', $teamId)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $members = User::where('team_id', $teamId)
            ->where('company_id', $user->company_id)
            ->select(['id', 'name', 'email', 'role', 'is_active', 'last_login_at', 'created_at'])
            ->orderBy('is_active', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json(['members' => $members]);
    }
}
