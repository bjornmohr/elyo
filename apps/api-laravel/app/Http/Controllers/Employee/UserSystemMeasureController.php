<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Resources\Employee\AssignedMeasureDetailResource;
use App\Http\Resources\Employee\AssignedMeasureResource;
use App\Models\UserSystemMeasure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSystemMeasureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $measures = $this->scopedQuery($request)
            ->with(['sourceTemplate', 'exercises'])
            ->orderBy('assigned_at')
            ->get();

        return response()->json(['data' => AssignedMeasureResource::collection($measures)]);
    }

    public function show(Request $request, int $userSystemMeasure): JsonResponse
    {
        $measure = $this->scopedQuery($request)
            ->with(['sourceTemplate', 'exercises.sourceExercise'])
            ->findOrFail($userSystemMeasure);

        return response()->json(['data' => new AssignedMeasureDetailResource($measure)]);
    }

    private function scopedQuery(Request $request)
    {
        return UserSystemMeasure::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [UserSystemMeasure::STATUS_ASSIGNED, UserSystemMeasure::STATUS_ACTIVE]);
    }
}
