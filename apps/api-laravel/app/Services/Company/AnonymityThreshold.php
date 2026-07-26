<?php

namespace App\Services\Company;

final class AnonymityThreshold
{
    private const PLATFORM_MINIMUM = 10;

    public static function resolve(?int $configuredThreshold): int
    {
        return max(self::PLATFORM_MINIMUM, $configuredThreshold ?? self::PLATFORM_MINIMUM);
    }
}
