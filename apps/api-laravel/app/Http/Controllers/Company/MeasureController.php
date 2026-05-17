<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CreateMeasureRequest;
use App\Http\Requests\Company\PatchMeasureRequest;
use App\Http\Resources\Company\MeasureResource;
use App\Models\Measure;
use App\Models\Team;
use Illuminate\Http\Request;

class MeasureController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('roles');
        $isManager = $user->hasRole('COMPANY_MANAGER') && !$user->hasAnyRole([\App\Enums\Role::COMPANY_ADMIN, \App\Enums\Role::COMPANY_OWNER]);
        $managedTeamId = $isManager
            ? Team::where('manager_id', $user->id)->where('company_id', $user->company_id)->value('id')
            : null;

        $query = Measure::where('company_id', $user->company_id);

        if ($isManager) {
            if (!$managedTeamId) {
                return MeasureResource::collection(collect());
            }
            $query->where(function ($q) use ($managedTeamId) {
                $q->whereNull('team_id')->orWhere('team_id', $managedTeamId);
            });
        }

        $measures = $query
            ->with('team:id,name')
            ->orderBy('suggested_at', 'desc')
            ->get();

        return MeasureResource::collection($measures);
    }

    public function store(CreateMeasureRequest $request)
    {
        $user = $request->user();
        $user->loadMissing('roles');
        $isManager = $user->hasRole('COMPANY_MANAGER') && !$user->hasAnyRole([\App\Enums\Role::COMPANY_ADMIN, \App\Enums\Role::COMPANY_OWNER]);
        $teamId = $request->teamId ?? null;

        if ($isManager) {
            $managedTeamId = Team::where('manager_id', $user->id)->where('company_id', $user->company_id)->value('id');
            if (!$managedTeamId) {
                abort(403);
            }
            $teamId = $managedTeamId;
        }

        $status = $request->status ?? 'ACTIVE';

        $measure = Measure::create([
            'company_id' => $user->company_id,
            'team_id' => $teamId,
            'title' => $request->title,
            'category' => $request->category,
            'description' => $request->description,
            'status' => $status,
            'started_at' => $status === 'ACTIVE' ? now() : null,
            'created_by' => $user->id,
        ]);

        return new MeasureResource($measure->load('team:id,name'));
    }

    public function update(PatchMeasureRequest $request, $id)
    {
        $user = $request->user();
        $user->loadMissing('roles');
        $isManager = $user->hasRole('COMPANY_MANAGER') && !$user->hasAnyRole([\App\Enums\Role::COMPANY_ADMIN, \App\Enums\Role::COMPANY_OWNER]);
        $managedTeamId = $isManager
            ? Team::where('manager_id', $user->id)->where('company_id', $user->company_id)->value('id')
            : null;

        $measure = Measure::where('id', $id)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        if ($isManager && (!$managedTeamId || (int) $measure->team_id !== (int) $managedTeamId)) {
            abort(403);
        }

        $validTransitions = [
            'SUGGESTED' => ['ACTIVE', 'DISMISSED'],
            'ACTIVE' => ['COMPLETED', 'DISMISSED'],
        ];

        $newStatus = $request->status;
        $allowed = $validTransitions[$measure->status] ?? [];

        if (!in_array($newStatus, $allowed)) {
            return response()->json(['error' => 'invalid_transition'], 400);
        }

        $updateData = ['status' => $newStatus];
        if ($newStatus === 'ACTIVE') $updateData['started_at'] = now();
        if ($newStatus === 'COMPLETED') $updateData['completed_at'] = now();

        $measure->update($updateData);

        return new MeasureResource($measure);
    }
}
