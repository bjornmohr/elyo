<?php

namespace App\Services\Health;

use App\Models\Health\WellbeingEntry;
use App\Services\Privacy\Exceptions\MappingNotFoundException;
use App\Services\Privacy\MappingServiceContract;
use App\Services\Privacy\PurposeCode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EntryCollection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Health-domain access to wellbeing check-ins (ELYO-110, ADR-003 D3).
 *
 * Everything here is subject-scoped: callers pass an identity user id, the
 * service resolves it to a `health_subject_id` through the mapping domain with
 * an explicit purpose code, and only the subject id ever reaches the health
 * database. No caller receives the subject id back.
 */
class WellbeingService
{
    /**
     * Canonical scale per ELYO-102 §3.1. The inverted-stress term uses
     * `SCALE_MAX + SCALE_MIN` so the inversion stays symmetric on the scale.
     */
    public const SCALE_MIN = 1;

    public const SCALE_MAX = 5;

    public function __construct(private readonly MappingServiceContract $mappingService) {}

    public function getPeriodKey(): string
    {
        return Carbon::now()->toDateString();
    }

    /**
     * Mean of mood, inverted stress and energy on the 1–5 scale, rounded to one
     * decimal: `(mood + (6 - stress) + energy) / 3`.
     */
    public function calculateScore(int $mood, int $stress, int $energy): float
    {
        $invertedStress = self::SCALE_MAX + self::SCALE_MIN - $stress;

        return round(($mood + $invertedStress + $energy) / 3, 1);
    }

    public function hasDailyCheckin(int $userId, ?string $periodKey = null): bool
    {
        return WellbeingEntry::query()
            ->where('health_subject_id', $this->resolveSubjectId($userId, PurposeCode::HEALTH_SELF_READ))
            ->where('period_key', $periodKey ?? $this->getPeriodKey())
            ->exists();
    }

    /**
     * @param  array{mood: int, stress: int, energy: int}  $data
     * @return WellbeingEntry|null null when the daily check-in already exists
     */
    public function submitCheckin(int $userId, array $data): ?WellbeingEntry
    {
        $subjectId = $this->resolveSubjectId($userId, PurposeCode::HEALTH_SELF_WRITE);
        $periodKey = $this->getPeriodKey();

        if ($this->hasSubjectCheckin($subjectId, $periodKey)) {
            return null;
        }

        try {
            return WellbeingEntry::create([
                'health_subject_id' => $subjectId,
                'period_key' => $periodKey,
                'mood' => $data['mood'],
                'stress' => $data['stress'],
                'energy' => $data['energy'],
                'score' => $this->calculateScore($data['mood'], $data['stress'], $data['energy']),
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                return null;
            }

            throw $exception;
        }
    }

    public function entryForPeriod(int $userId, ?string $periodKey = null): ?WellbeingEntry
    {
        return WellbeingEntry::query()
            ->where('health_subject_id', $this->resolveSubjectId($userId, PurposeCode::HEALTH_SELF_READ))
            ->where('period_key', $periodKey ?? $this->getPeriodKey())
            ->latest()
            ->first();
    }

    /**
     * Newest entries first — the dashboard's sparkline source.
     *
     * @return EntryCollection<int, WellbeingEntry>
     */
    public function recentEntries(int $userId, int $limit): EntryCollection
    {
        return WellbeingEntry::query()
            ->where('health_subject_id', $this->resolveSubjectId($userId, PurposeCode::HEALTH_SELF_READ))
            ->orderByDesc('created_at')
            ->take($limit)
            ->get();
    }

    /**
     * Oldest entries first — the history endpoint's chronological order.
     *
     * @return EntryCollection<int, WellbeingEntry>
     */
    public function historyEntries(int $userId, int $limit): EntryCollection
    {
        return WellbeingEntry::query()
            ->where('health_subject_id', $this->resolveSubjectId($userId, PurposeCode::HEALTH_SELF_READ))
            ->orderBy('created_at')
            ->take($limit)
            ->get();
    }

    /**
     * Distinct period keys, newest first. This is the streak source for
     * `App\Services\PointsService`: points stay identity-side and never see a
     * subject id or an entry.
     *
     * @return Collection<int, string>
     */
    public function checkinPeriodKeys(int $userId): Collection
    {
        return WellbeingEntry::query()
            ->where('health_subject_id', $this->resolveSubjectId($userId, PurposeCode::HEALTH_SELF_READ))
            ->orderByDesc('period_key')
            ->distinct()
            ->pluck('period_key');
    }

    protected function hasSubjectCheckin(string $subjectId, string $periodKey): bool
    {
        return WellbeingEntry::query()
            ->where('health_subject_id', $subjectId)
            ->where('period_key', $periodKey)
            ->exists();
    }

    /**
     * Resolves the caller's own subject. A missing mapping is repaired in place:
     * provisioning is idempotent by design (ADR-003 D5, prompt 05), so a subject
     * that was never provisioned — or whose provisioning failed after the
     * identity commit — must not turn into a failed check-in. Both the failed
     * resolve and the repair are audited by the mapping service.
     */
    private function resolveSubjectId(int $userId, PurposeCode $purpose): string
    {
        try {
            return $this->mappingService->resolveOwnSubject($userId, $purpose);
        } catch (MappingNotFoundException) {
            Log::warning('[HEALTH] Missing subject mapping repaired during self-service access.', [
                'purpose' => $purpose->value,
            ]);

            return $this->mappingService->provisionOwnSubject($userId, PurposeCode::PROVISIONING);
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return in_array($sqlState, ['23000', '23505'], true);
    }
}
