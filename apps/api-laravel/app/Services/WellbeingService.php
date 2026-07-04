<?php

namespace App\Services;

use App\Models\User;
use App\Models\WellbeingEntry;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

class WellbeingService
{
    public function getPeriodKey(User $user): string
    {
        return Carbon::now()->toDateString();
    }

    public function calculateScore(int $mood, int $stress, int $energy): float
    {
        // Canonical 1-5 scale; stress is inverted (lower is better).
        return round((($mood + (6 - $stress) + $energy) / 3), 1);
    }

    public function hasDailyCheckin(User $user, ?string $periodKey = null): bool
    {
        return WellbeingEntry::where('user_id', $user->id)
            ->where('period_key', $periodKey ?? $this->getPeriodKey($user))
            ->exists();
    }

    public function submitCheckin(User $user, array $data): ?WellbeingEntry
    {
        $periodKey = $this->getPeriodKey($user);
        $score = $this->calculateScore($data['mood'], $data['stress'], $data['energy']);

        if ($this->hasDailyCheckin($user, $periodKey)) {
            return null;
        }

        try {
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
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                return null;
            }

            throw $exception;
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return in_array($sqlState, ['23000', '23505'], true);
    }
}
