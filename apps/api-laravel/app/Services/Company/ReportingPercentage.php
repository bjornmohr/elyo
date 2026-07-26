<?php

namespace App\Services\Company;

/**
 * Percentage rounding for company reporting aggregates (ADR-001 §2.5).
 *
 * Released percentages are coarsened to 5-point steps so a released rate
 * cannot be inverted back into an exact contributor count. This applies to
 * reporting surfaces only — an individual's own progress figures are not
 * reporting aggregates and stay exact.
 */
final class ReportingPercentage
{
    private const STEP = 5;

    public static function of(int $count, int $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        return (int) (round((($count / $total) * 100) / self::STEP) * self::STEP);
    }
}
