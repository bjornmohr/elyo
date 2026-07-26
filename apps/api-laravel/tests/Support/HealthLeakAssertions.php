<?php

namespace Tests\Support;

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\ExpectationFailedException;

trait HealthLeakAssertions
{
    /**
     * @param  list<string>  $knownHealthSubjectIds
     */
    protected function assertResponseHasNoHealthLeaks(
        TestResponse $response,
        string $endpoint,
        array $knownHealthSubjectIds = [],
    ): void {
        if (in_array($response->getStatusCode(), [204, 205], true)) {
            $this->assertTrue(
                $response->getContent() === '',
                "Unexpected response content at $ (no-content response for {$endpoint}).",
            );

            return;
        }

        $this->assertPayloadHasNoHealthLeaks(
            $response->json(),
            '$',
            $endpoint,
            $knownHealthSubjectIds,
            HealthLeakAllowlist::releasedAggregatePaths($response),
        );
    }

    /**
     * @param  list<string>  $knownHealthSubjectIds
     * @param  list<string>  $releasedAggregatePaths
     */
    private function assertPayloadHasNoHealthLeaks(
        mixed $payload,
        string $path,
        string $endpoint,
        array $knownHealthSubjectIds,
        array $releasedAggregatePaths,
    ): void {
        if (is_array($payload)) {
            $normalizedKeys = [];

            foreach (array_keys($payload) as $key) {
                if (is_string($key)) {
                    $normalizedKeys[] = Str::snake($key);
                }
            }

            foreach (ForbiddenHealthPatternCatalog::COMPOUND_PATTERNS as $pattern) {
                if (array_diff($pattern['required_keys'], $normalizedKeys) === []) {
                    $this->rejectUnlessAllowlisted(
                        $pattern['id'],
                        $endpoint,
                        $path,
                        $releasedAggregatePaths,
                    );
                }
            }

            $objectContext = $this->contextFor($endpoint, $path, $normalizedKeys);

            foreach ($payload as $key => $value) {
                $childPath = $path.'.'.$key;

                if (is_string($key)) {
                    $normalizedKey = Str::snake($key);

                    foreach (ForbiddenHealthPatternCatalog::KEY_PATTERNS as $pattern) {
                        if (preg_match($pattern['regex'], $normalizedKey) === 1) {
                            $this->rejectUnlessAllowlisted(
                                $pattern['id'],
                                $endpoint,
                                $childPath,
                                $releasedAggregatePaths,
                            );
                        }
                    }

                    foreach (ForbiddenHealthPatternCatalog::CONTEXTUAL_KEY_PATTERNS as $pattern) {
                        if (
                            preg_match($pattern['key_regex'], $normalizedKey) === 1
                            && preg_match($pattern['context_regex'], $objectContext) === 1
                        ) {
                            $this->rejectUnlessAllowlisted(
                                $pattern['id'],
                                $endpoint,
                                $childPath,
                                $releasedAggregatePaths,
                            );
                        }
                    }
                }

                $this->assertPayloadHasNoHealthLeaks(
                    $value,
                    $childPath,
                    $endpoint,
                    $knownHealthSubjectIds,
                    $releasedAggregatePaths,
                );
            }

            return;
        }

        if (! is_string($payload)) {
            return;
        }

        foreach ($knownHealthSubjectIds as $knownHealthSubjectId) {
            if (hash_equals($knownHealthSubjectId, $payload)) {
                $this->rejectUnlessAllowlisted(
                    ForbiddenHealthPatternCatalog::KNOWN_HEALTH_SUBJECT['id'],
                    $endpoint,
                    $path,
                    $releasedAggregatePaths,
                );
            }
        }

        $healthContext = $this->contextFor($endpoint, $path);

        if (
            Str::isUlid($payload)
            && preg_match(
                ForbiddenHealthPatternCatalog::HEALTH_CONTEXT_ULID['context_regex'],
                $healthContext,
            ) === 1
        ) {
            $this->rejectUnlessAllowlisted(
                ForbiddenHealthPatternCatalog::HEALTH_CONTEXT_ULID['id'],
                $endpoint,
                $path,
                $releasedAggregatePaths,
            );
        }
    }

    /**
     * Build the lowercase context a contextual pattern is matched against.
     *
     * Path segments are snake_cased so camelCase keys expose their word
     * boundaries: `labSummary` becomes `lab_summary`, which lets a context
     * token anchor on `lab` without also matching `label` or `available`.
     * The reported path stays verbatim — only the context is normalized.
     *
     * @param  list<string>  $normalizedKeys
     */
    private function contextFor(string $endpoint, string $path, array $normalizedKeys = []): string
    {
        $normalizedPath = implode('.', array_map(
            static fn (string $segment): string => Str::snake($segment),
            explode('.', $path),
        ));

        return strtolower(
            $endpoint.' '.$normalizedPath.' '.implode(' ', $normalizedKeys),
        );
    }

    /**
     * @param  list<string>  $releasedAggregatePaths
     */
    private function rejectUnlessAllowlisted(
        string $pattern,
        string $endpoint,
        string $path,
        array $releasedAggregatePaths,
    ): void {
        if (
            in_array($path, $releasedAggregatePaths, true)
            && HealthLeakAllowlist::allows($pattern, $endpoint, $path)
        ) {
            return;
        }

        throw new ExpectationFailedException(
            "Forbidden health pattern [{$pattern}] at {$path} (catalog "
            .ForbiddenHealthPatternCatalog::VERSION.').',
        );
    }
}
