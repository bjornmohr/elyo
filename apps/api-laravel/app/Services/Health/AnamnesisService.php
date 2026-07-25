<?php

namespace App\Services\Health;

use App\Models\Health\AnamnesisProfile;
use App\Services\Privacy\MappingServiceContract;
use App\Services\Privacy\PurposeCode;

/**
 * Health-domain access to anamnesis profiles (ELYO-91 prompt 08a, ADR-003 D8).
 *
 * Same shape as `WellbeingService`: callers pass an identity user id, the
 * service resolves it to a `health_subject_id` through the mapping domain with
 * an explicit purpose code, and the subject id never leaves the health domain.
 */
class AnamnesisService
{
    use ResolvesOwnSubject;

    /**
     * The anamnesis fields that count towards the completion percentage.
     * `chronic_patterns` is deliberately excluded: an empty list is a valid
     * answer, so it cannot distinguish "unanswered" from "nothing applies".
     */
    private const COMPLETION_FIELDS = [
        'birth_year',
        'biological_sex',
        'activity_level',
        'sleep_quality',
        'stress_tendency',
        'smoking_status',
        'nutrition_type',
        'has_medication',
    ];

    public function __construct(private readonly MappingServiceContract $mappingService) {}

    protected function mappingService(): MappingServiceContract
    {
        return $this->mappingService;
    }

    public function profileFor(int $userId): ?AnamnesisProfile
    {
        return AnamnesisProfile::query()
            ->where('health_subject_id', $this->resolveSubjectId($userId, PurposeCode::HEALTH_SELF_READ))
            ->first();
    }

    /**
     * Creates or updates the caller's anamnesis. `created` tells the caller
     * whether this was the first completed anamnesis, which is what the
     * identity-side points rule keys on — the caller never learns the subject.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{profile: AnamnesisProfile, created: bool}
     */
    public function saveProfile(int $userId, array $attributes): array
    {
        $subjectId = $this->resolveSubjectId($userId, PurposeCode::HEALTH_SELF_WRITE);
        $attributes['completion_pct'] = $this->completionPercentage($attributes);

        $profile = AnamnesisProfile::query()->updateOrCreate(
            ['health_subject_id' => $subjectId],
            $attributes,
        );

        return [
            'profile' => $profile,
            'created' => $profile->wasRecentlyCreated,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function completionPercentage(array $attributes): int
    {
        $filled = collect(self::COMPLETION_FIELDS)
            ->filter(fn (string $field): bool => ($attributes[$field] ?? null) !== null && $attributes[$field] !== '')
            ->count();

        return (int) round(($filled / count(self::COMPLETION_FIELDS)) * 100);
    }
}
