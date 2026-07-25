<?php

namespace App\Services;

use App\Models\PointTransaction;
use App\Models\User;
use App\Models\UserPoints;
use App\Services\Health\WellbeingService;
use Carbon\Carbon;

class PointsService
{
    /**
     * Points stay identity-side; only the consecutive-days source lives in the
     * health domain (subject-scoped) since check-ins moved there (ADR-003 D3).
     */
    public function __construct(private readonly WellbeingService $wellbeingService) {}

    public function awardPoints(User $user, string $reason): void
    {
        $points = PointSettingsService::resolvePointMap()[$reason] ?? 0;

        if ($points === 0) {
            return;
        }

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

    public function awardPointsOnce(User $user, string $reason): void
    {
        if (PointTransaction::where('user_id', $user->id)->where('reason', $reason)->exists()) {
            return;
        }

        $this->awardPoints($user, $reason);
    }

    public function updateStreak(User $user): int
    {
        $streak = $this->calculateStreak($user);

        UserPoints::updateOrCreate(
            ['user_id' => $user->id],
            [
                'streak' => $streak,
                'last_checkin' => Carbon::now(),
            ]
        );

        return $streak;
    }

    public function calculateStreak(User $user): int
    {
        $days = $this->wellbeingService->checkinPeriodKeys($user->id)
            ->filter(fn (string $periodKey) => $this->isValidDailyPeriodKey($periodKey))
            ->filter(fn (string $periodKey) => $this->isWorkdayPeriodKey($periodKey))
            ->unique()
            ->values();

        if ($days->isEmpty()) {
            return 0;
        }

        $streak = 1;
        $previous = Carbon::parse($days[0])->startOfDay();

        for ($i = 1; $i < $days->count(); $i++) {
            $current = Carbon::parse($days[$i])->startOfDay();
            if ($current->equalTo($this->previousExpectedWorkday($previous))) {
                $streak++;
                $previous = $current;

                continue;
            }
            break;
        }

        return $streak;
    }

    public function isValidDailyPeriodKey(string $periodKey): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodKey) !== 1) {
            return false;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $periodKey);
        } catch (\Throwable) {
            return false;
        }

        return $date instanceof Carbon && $date->format('Y-m-d') === $periodKey;
    }

    public function isWorkdayPeriodKey(string $periodKey): bool
    {
        if (! $this->isValidDailyPeriodKey($periodKey)) {
            return false;
        }

        return Carbon::createFromFormat('Y-m-d', $periodKey)->isWeekday();
    }

    public function previousExpectedWorkday(Carbon $date): Carbon
    {
        $previous = (clone $date)->subDay()->startOfDay();

        while (! $previous->isWeekday()) {
            $previous->subDay();
        }

        return $previous;
    }
}
