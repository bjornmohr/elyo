<?php

namespace App\Services;

use App\Models\User;
use App\Models\WellbeingEntry;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

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

    /**
     * Demo mode: re-anchor daily entries forward in whole days so the newest
     * lands on yesterday (today stays open for the check-in CTA). Keeps seeded
     * history inside date-anchored UI windows every week. Presentation only —
     * nothing is persisted. No-op in prod or when the data is already current.
     *
     * @param  Collection<int, WellbeingEntry>  $entries  daily entries (period_key = Y-m-d)
     * @return Collection<int, WellbeingEntry>
     */
    public function shiftEntriesToPresent(Collection $entries): Collection
    {
        if (config('elyo.data_mode') !== 'demo' || $entries->isEmpty()) {
            return $entries;
        }

        $latestKey = $entries->pluck('period_key')
            ->filter(fn (string $key) => $this->isValidDailyKey($key))
            ->max();

        if ($latestKey === null) {
            return $entries;
        }

        $offsetDays = (int) Carbon::parse($latestKey)->diffInDays(Carbon::yesterday(), false);

        if ($offsetDays <= 0) {
            return $entries;
        }

        return $entries->map(function (WellbeingEntry $entry) use ($offsetDays) {
            if ($this->isValidDailyKey($entry->period_key)) {
                $entry->period_key = Carbon::parse($entry->period_key)->addDays($offsetDays)->toDateString();
                $entry->created_at = $entry->created_at?->copy()->addDays($offsetDays);
            }

            return $entry;
        });
    }

    private function isValidDailyKey(string $periodKey): bool
    {
        $date = \DateTime::createFromFormat('Y-m-d', $periodKey);

        return $date instanceof \DateTime && $date->format('Y-m-d') === $periodKey;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return in_array($sqlState, ['23000', '23505'], true);
    }
}
