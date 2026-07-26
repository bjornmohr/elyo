<?php

namespace Tests\Support;

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * ELYO-91 prompt 09: company and admin responses must never carry wellbeing
 * values. The check sweeps the whole decoded payload: semantic health field
 * names and strings are forbidden, and every numeric value needs an explicit
 * identity-side path allowlist entry. Reusable by the privacy regression suite
 * (prompt 16).
 */
trait AssertsNoWellbeingValues
{
    private const WELLBEING_PATTERN = '/mood|stress|energy|score/i';

    /**
     * @param  list<string>  $allowedNumericPaths
     */
    protected function assertResponseHasNoWellbeingValues(
        TestResponse $response,
        array $allowedNumericPaths = [],
    ): void {
        $this->assertPayloadHasNoWellbeingValues($response->json(), '$', $allowedNumericPaths);
    }

    /**
     * @param  list<string>  $allowedNumericPaths
     */
    private function assertPayloadHasNoWellbeingValues(
        mixed $payload,
        string $path,
        array $allowedNumericPaths,
    ): void {
        if (is_array($payload)) {
            foreach ($payload as $key => $value) {
                $childPath = $path.'.'.$key;

                if (is_string($key)) {
                    $this->assertDoesNotMatchRegularExpression(
                        self::WELLBEING_PATTERN,
                        $key,
                        "Wellbeing field {$childPath} must not appear in this response.",
                    );
                }

                $this->assertPayloadHasNoWellbeingValues($value, $childPath, $allowedNumericPaths);
            }

            return;
        }

        if (is_string($payload)) {
            $this->assertDoesNotMatchRegularExpression(
                self::WELLBEING_PATTERN,
                $payload,
                "Wellbeing value at {$path} must not appear in this response.",
            );

            if (is_numeric($payload)) {
                $this->assertNumericPathIsAllowed($path, $allowedNumericPaths);
            }

            return;
        }

        if (is_int($payload) || is_float($payload)) {
            $this->assertNumericPathIsAllowed($path, $allowedNumericPaths);
        }
    }

    /**
     * @param  list<string>  $allowedNumericPaths
     */
    private function assertNumericPathIsAllowed(string $path, array $allowedNumericPaths): void
    {
        $isAllowedIdentityValue = collect($allowedNumericPaths)
            ->contains(fn (string $allowedPath): bool => Str::is($allowedPath, $path));

        $this->assertTrue(
            $isAllowedIdentityValue,
            "Unexpected numeric value at {$path}; company/admin responses must explicitly allow identity-side numeric fields.",
        );
    }
}
