<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPoints;
use App\Models\PointTransaction;
use App\Models\WellbeingEntry;
use Carbon\Carbon;

class PointsService
{
    public function awardPoints(User $user, string $reason): void
    {
        $points = match ($reason) {
            'daily_checkin' => 10,
            'anamnesis_completed' => 100,
            'medical_document_upload' => 25,
            'streak_7days' => 50,
            'streak_30days' => 200,
            default => 0,
        };

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
        // Simple streak logic: count consecutive days/weeks with checkins
        // For now, let's just return a count of recent entries as a placeholder
        // like the legacy dashboard did (though it just returned entries.length)

        return WellbeingEntry::where('user_id', $user->id)->count();
    }
}
