<?php

namespace App\Services\Insights\Demo;

use App\Models\Company;

/**
 * Applies deterministic per-company jitter to demo base values. The base
 * dataset holds the exact mockup numbers; the reference company (demo-gmbh)
 * gets them unchanged, every other company gets stable variations.
 * Nulls always stay null so anonymity suppression survives the jitter.
 */
class DemoVariance
{
    public const REFERENCE_COMPANY_SLUG = 'demo-gmbh';

    private SeededRandom $rng;

    private bool $identity;

    public function __construct(?Company $company, string $module)
    {
        $seedSource = ($company?->slug ?? (string) ($company?->id ?? 'unknown')).':'.$module;
        $this->rng = new SeededRandom(crc32($seedSource));
        $this->identity = ($company?->slug ?? null) === self::REFERENCE_COMPANY_SLUG;
    }

    public function isIdentity(): bool
    {
        return $this->identity;
    }

    public function rng(): SeededRandom
    {
        return $this->rng;
    }

    /** Percentage/rate 0-100, +/- spread points. */
    public function percent(int|float|null $value, float $spread = 8, int $precision = 0): int|float|null
    {
        if ($value === null) {
            return null;
        }
        $jittered = $this->identity ? (float) $value : $value + $this->rng->signed() * $spread;

        return $this->round(max(0, min(100, $jittered)), $precision, is_int($value));
    }

    /** Absolute count, +/- relative fraction, never below zero. */
    public function count(?int $value, float $relative = 0.15): ?int
    {
        if ($value === null) {
            return null;
        }
        if ($this->identity) {
            return $value;
        }

        return max(0, (int) round($value * (1 + $this->rng->signed() * $relative)));
    }

    /** Score 0-100, +/- spread points. */
    public function score(?int $value, float $spread = 6): ?int
    {
        if ($value === null) {
            return null;
        }
        if ($this->identity) {
            return $value;
        }

        return (int) round(max(0, min(100, $value + $this->rng->signed() * $spread)));
    }

    /** Star rating 1-5, +/- 1 step. */
    public function rating(?int $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if ($this->identity) {
            return $value;
        }

        return max(1, min(5, $value + $this->rng->intBetween(-1, 1)));
    }

    /** Trend in percent points, +/- spread. */
    public function trend(int|float|null $value, float $spread = 3, int $precision = 0): int|float|null
    {
        if ($value === null) {
            return null;
        }
        if ($this->identity) {
            return $value;
        }

        return $this->round($value + $this->rng->signed() * $spread, $precision, is_int($value));
    }

    /** Day durations, +/- relative fraction. */
    public function days(int|float|null $value, float $relative = 0.25, int $precision = 1): int|float|null
    {
        if ($value === null) {
            return null;
        }
        if ($this->identity) {
            return $value;
        }

        return $this->round(max(0, $value * (1 + $this->rng->signed() * $relative)), $precision, false);
    }

    private function round(float $value, int $precision, bool $forceInt): int|float
    {
        if ($forceInt || $precision === 0) {
            return (int) round($value);
        }

        return round($value, $precision);
    }
}
