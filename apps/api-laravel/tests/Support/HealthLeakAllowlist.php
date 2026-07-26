<?php

namespace Tests\Support;

use App\Services\Company\AnonymityThreshold;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Reviewed exceptions for legitimate reporting aggregates (ADR-001 §2.5).
 *
 * Every entry must name the catalog pattern, endpoint glob, JSON path glob,
 * review ticket and aggregate-specific rationale. Broad endpoint or `$.*`
 * exceptions are not acceptable. A legitimate aggregate is admitted here,
 * never by deleting or weakening ForbiddenHealthPatternCatalog.
 */
final class HealthLeakAllowlist
{
    /**
     * @var list<array{
     *     pattern: string,
     *     endpoint: string,
     *     path: string,
     *     ticket: string,
     *     rationale: string
     * }>
     */
    public const ENTRIES = [
        [
            'pattern' => 'score_in_health_context',
            'endpoint' => '/api/company/surveys/{id}/results',
            'path' => '$.data.questions.*.distribution.*.value',
            'ticket' => 'ELYO-111',
            'rationale' => 'Scale buckets are released only after the effective minimum of 10 and bucket suppression checks pass.',
        ],
    ];

    public static function allows(string $pattern, string $endpoint, string $path): bool
    {
        foreach (self::ENTRIES as $entry) {
            if (
                $entry['pattern'] === $pattern
                && Str::is($entry['endpoint'], $endpoint)
                && Str::is($entry['path'], $path)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return exact aggregate paths whose response state proves that the
     * reviewed survey exception is eligible.
     *
     * @return list<string>
     */
    public static function releasedAggregatePaths(TestResponse $response): array
    {
        if (
            $response->getStatusCode() < 200
            || $response->getStatusCode() >= 300
        ) {
            return [];
        }

        $payload = $response->json();
        $data = is_array($payload) ? ($payload['data'] ?? null) : null;

        if (
            ! is_array($data)
            || ($data['isAboveThreshold'] ?? null) !== true
            || ! is_array($data['questions'] ?? null)
        ) {
            return [];
        }

        $paths = [];

        foreach ($data['questions'] as $questionIndex => $question) {
            if (
                ! is_array($question)
                || ($question['type'] ?? null) !== 'SCALE'
                || ($question['isSuppressed'] ?? null) !== false
                || ! is_array($question['distribution'] ?? null)
            ) {
                continue;
            }

            foreach ($question['distribution'] as $bucketIndex => $bucket) {
                if (
                    ! is_array($bucket)
                    || ! is_int($bucket['count'] ?? null)
                    || $bucket['count'] < AnonymityThreshold::categoryMinimum()
                ) {
                    continue;
                }

                $paths[] = '$.data.questions.'.$questionIndex
                    .'.distribution.'.$bucketIndex.'.value';
            }
        }

        return $paths;
    }
}
