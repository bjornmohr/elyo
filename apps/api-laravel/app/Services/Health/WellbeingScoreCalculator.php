<?php

namespace App\Services\Health;

final class WellbeingScoreCalculator
{
    public const SCALE_MIN = 1;

    public const SCALE_MAX = 5;

    /**
     * Mean of mood, inverted stress and energy on the canonical 1–5 scale.
     */
    public static function calculate(int $mood, int $stress, int $energy): float
    {
        $invertedStress = self::SCALE_MAX + self::SCALE_MIN - $stress;

        return round(($mood + $invertedStress + $energy) / 3, 1);
    }
}
