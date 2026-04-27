<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\CheckinRequest;
use App\Http\Requests\Employee\UpdateProfileRequest;
use App\Http\Resources\Employee\WellbeingEntryResource;
use App\Services\WellbeingService;
use App\Services\PointsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmployeeController extends Controller
{
    public function __construct(
        protected WellbeingService $wellbeingService,
        protected PointsService $pointsService
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $entries = $user->wellbeingEntries()
            ->orderBy('created_at', 'desc')
            ->take(7)
            ->get();

        $latest = $entries->first();
        $streakCount = $this->pointsService->calculateStreak($user);

        return response()->json([
            'latest' => $latest ? new WellbeingEntryResource($latest) : null,
            'entries' => WellbeingEntryResource::collection($entries->reverse()),
            'streakCount' => $streakCount,
        ]);
    }

    public function checkin(CheckinRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->company_id) {
            return response()->json(['error' => 'Employee must belong to a company'], 403);
        }

        $entry = $this->wellbeingService->submitCheckin($user, $request->validated());

        try {
            $this->pointsService->awardPoints($user, 'daily_checkin');
            $streak = $this->pointsService->updateStreak($user);

            if ($streak === 7) {
                $this->pointsService->awardPoints($user, 'streak_7days');
            } elseif ($streak === 30) {
                $this->pointsService->awardPoints($user, 'streak_30days');
            }
        } catch (\Exception $e) {
            // Log error but don't fail the checkin
            \Log::error('[CHECKIN] Points award failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'score' => $entry->score,
            'periodKey' => $entry->period_key,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $limit = $request->query('limit', 20);
        $entries = $request->user()->wellbeingEntries()
            ->orderBy('created_at', 'asc')
            ->take($limit)
            ->get();

        return response()->json([
            'entries' => WellbeingEntryResource::collection($entries),
        ]);
    }

    public function getProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]
        ]);
    }
}
