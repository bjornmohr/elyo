<?php

namespace Tests\Support;

use Illuminate\Support\Str;

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
}
