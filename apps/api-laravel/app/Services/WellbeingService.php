<?php

namespace App\Services;

use App\Models\User;
use App\Models\WellbeingEntry;
use App\Enums\CheckinFrequency;
use Illuminate\Support\Str;
use Carbon\Carbon;

class WellbeingService
{
    public function getPeriodKey(User $user): string
    {
        $now = Carbon::now();
        $frequency = $user->company->checkin_frequency ?? CheckinFrequency::WEEKLY;

        if ($frequency === CheckinFrequency::DAILY) {
            return $now->toDateString();
        }

        // WEEKLY: ISO week key e.g. "2024-W12"
        return $now->format('Y-\WW');
    }

    public function calculateScore(int $mood, int $stress, int $energy): float
    {
        return round((($mood + (11 - $stress) + $energy) / 3), 1);
    }

    public function submitCheckin(User $user, array $data): WellbeingEntry
    {
        $periodKey = $this->getPeriodKey($user);
        $score = $this->calculateScore($data['mood'], $data['stress'], $data['energy']);

        return WellbeingEntry::updateOrCreate(
            [
                'user_id' => $user->id,
                'period_key' => $periodKey,
            ],
            [
                'id' => (string) Str::orderedUuid(),
                'company_id' => $user->company_id,
                'mood' => $data['mood'],
                'stress' => $data['stress'],
                'energy' => $data['energy'],
                'score' => $score,
                'note' => $data['note'] ?? null,
            ]
        );
    }
}
