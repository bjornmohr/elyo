<?php

namespace App\Services\Insights\Demo;

/**
 * Deterministic 32-bit LCG, independent of PHP's global RNG state
 * (mt_srand would leak across unrelated code paths).
 */
class SeededRandom
{
    private int $state;

    public function __construct(int $seed)
    {
        $this->state = $seed & 0xFFFFFFFF;
    }

    /** Uniform float in [0, 1). */
    public function next(): float
    {
        // Numerical Recipes LCG constants; products stay within 63 bits.
        $this->state = ($this->state * 1664525 + 1013904223) & 0xFFFFFFFF;

        return $this->state / 4294967296;
    }

    /** Uniform float in [-1, 1). */
    public function signed(): float
    {
        return $this->next() * 2 - 1;
    }

    public function intBetween(int $min, int $max): int
    {
        return $min + (int) floor($this->next() * ($max - $min + 1));
    }
}
