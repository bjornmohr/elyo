<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPoints;
use App\Models\PointTransaction;
use App\Models\PointSetting;
use App\Models\WellbeingEntry;
use Carbon\Carbon;

class PointsService
{
    public const DEFAULT_POINTS = [
        'daily_checkin' => 10,
        'anamnesis_completed' => 100,
        'medical_document_upload' => 25,
        'streak_7days' => 50,
        'streak_30days' => 200,
    ];

    public function awardPoints(User $user, string $reason): void
    {
        $points = self::resolvePointMap()[$reason] ?? 0;

        if ($points === 0) return;

        PointTransaction::create([
            'user_id' => $user->id,
            'points' => $points,
            'reason' => $reason,
        ]);

        $userPoints = UserPoints::firstOrCreate(
            ['user_id' => $user->id],
            ['total' => 0, 'streak' => 0]
        );

        $userPoints->increment('total', $points);
    }

    public function updateStreak(User $user): int
    {
        $streak = $this->calculateStreak($user);

        UserPoints::updateOrCreate(
            ['user_id' => $user->id],
            [
                'streak' => $streak,
                'last_checkin' => Carbon::now()
            ]
        );

        return $streak;
    }

    public function calculateStreak(User $user): int
    {
        $days = WellbeingEntry::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get(['created_at'])
            ->map(fn ($entry) => $entry->created_at->toDateString())
            ->unique()
            ->values();

        if ($days->isEmpty()) {
            return 0;
        }

        $streak = 1;
        $previous = Carbon::parse($days[0])->startOfDay();

        for ($i = 1; $i < $days->count(); $i++) {
            $current = Carbon::parse($days[$i])->startOfDay();
            if ($current->equalTo((clone $previous)->subDay())) {
                $streak++;
                $previous = $current;
                continue;
            }
            break;
        }

        return $streak;
    }

    public static function resolvePointMap(): array
    {
        $configured = PointSetting::query()
            ->whereIn('action', array_keys(self::DEFAULT_POINTS))
            ->pluck('points', 'action')
            ->all();

        return array_merge(self::DEFAULT_POINTS, $configured);
    }
}
