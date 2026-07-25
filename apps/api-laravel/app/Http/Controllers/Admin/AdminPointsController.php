<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePointSettingsRequest;
use App\Models\PointSetting;
use App\Services\PointSettingsService;
use Illuminate\Http\JsonResponse;

class AdminPointsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => PointSettingsService::resolvePointMap(),
        ]);
    }

    public function update(UpdatePointSettingsRequest $request): JsonResponse
    {
        foreach ($request->validated() as $action => $points) {
            PointSetting::updateOrCreate(
                ['action' => $action],
                ['points' => $points]
            );
        }

        return response()->json([
            'data' => PointSettingsService::resolvePointMap(),
        ]);
    }
}
