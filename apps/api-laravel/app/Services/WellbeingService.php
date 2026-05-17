<?php

namespace App\Services;

use App\Models\User;
use App\Models\WellbeingEntry;
use Carbon\Carbon;

class WellbeingService
{
    public function getPeriodKey(User $user): string
    {
        return Carbon::now()->toDateString();
    }

    public function calculateScore(int $mood, int $stress, int $energy): float
    {
        return round((($mood + (11 - $stress) + $energy) / 3), 1);
    }

    public function submitCheckin(User $user, array $data): WellbeingEntry
    {
        $periodKey = $this->getPeriodKey($user);
        $score = $this->calculateScore($data['mood'], $data['stress'], $data['energy']);

        return WellbeingEntry::create([
            'user_id' => $user->id,
            'period_key' => $periodKey,
            'company_id' => $user->company_id,
            'mood' => $data['mood'],
            'stress' => $data['stress'],
            'energy' => $data['energy'],
            'score' => $score,
            'note' => $data['note'] ?? null,
        ]);
    }
}
