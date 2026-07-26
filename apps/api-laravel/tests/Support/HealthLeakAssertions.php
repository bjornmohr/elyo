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
        );
    }

    /**
     * @param  list<string>  $knownHealthSubjectIds
     */
    private function assertPayloadHasNoHealthLeaks(
        mixed $payload,
        string $path,
        string $endpoint,
        array $knownHealthSubjectIds,
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
                    $this->rejectUnlessAllowlisted($pattern['id'], $endpoint, $path);
                }
            }

            $objectContext = strtolower($endpoint.' '.$path.' '.implode(' ', $normalizedKeys));

            foreach ($payload as $key => $value) {
                $childPath = $path.'.'.$key;

                if (is_string($key)) {
                    $normalizedKey = Str::snake($key);

                    foreach (ForbiddenHealthPatternCatalog::KEY_PATTERNS as $pattern) {
                        if (preg_match($pattern['regex'], $normalizedKey) === 1) {
                            $this->rejectUnlessAllowlisted($pattern['id'], $endpoint, $childPath);
                        }
                    }

                    foreach (ForbiddenHealthPatternCatalog::CONTEXTUAL_KEY_PATTERNS as $pattern) {
                        if (
                            preg_match($pattern['key_regex'], $normalizedKey) === 1
                            && preg_match($pattern['context_regex'], $objectContext) === 1
                        ) {
                            $this->rejectUnlessAllowlisted($pattern['id'], $endpoint, $childPath);
                        }
                    }
                }

                $this->assertPayloadHasNoHealthLeaks(
                    $value,
                    $childPath,
                    $endpoint,
                    $knownHealthSubjectIds,
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
                );
            }
        }

        $healthContext = strtolower($endpoint.' '.$path);

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
            );
        }
    }

    private function rejectUnlessAllowlisted(string $pattern, string $endpoint, string $path): void
    {
        if (HealthLeakAllowlist::allows($pattern, $endpoint, $path)) {
            return;
        }

        throw new ExpectationFailedException(
            "Forbidden health pattern [{$pattern}] at {$path} (catalog "
            .ForbiddenHealthPatternCatalog::VERSION.').',
        );
    }
}
