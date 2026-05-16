<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CreateMeasureRequest;
use App\Http\Requests\Company\PatchMeasureRequest;
use App\Http\Resources\Company\MeasureResource;
use App\Models\Measure;
use Illuminate\Http\Request;

class MeasureController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('roles');
        if (!$user->hasAnyRole([\App\Enums\Role::COMPANY_ADMIN, \App\Enums\Role::COMPANY_OWNER])) {
            abort(403);
        }

        $measures = Measure::where('company_id', $user->company_id)
            ->with('team:id,name')
            ->orderBy('suggested_at', 'desc')
            ->get();

        return MeasureResource::collection($measures);
    }

    public function store(CreateMeasureRequest $request)
    {
        $measure = Measure::create([
            'company_id' => $request->user()->company_id,
            'team_id' => $request->teamId ?? null,
            'title' => $request->title,
            'category' => $request->category,
            'description' => $request->description,
            'status' => 'ACTIVE',
            'started_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        return new MeasureResource($measure);
    }

    public function update(PatchMeasureRequest $request, $id)
    {
        $user = $request->user();
        $user->loadMissing('roles');
        if (!$user->hasAnyRole([\App\Enums\Role::COMPANY_ADMIN, \App\Enums\Role::COMPANY_OWNER])) {
            abort(403);
        }

        $measure = Measure::where('id', $id)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

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
