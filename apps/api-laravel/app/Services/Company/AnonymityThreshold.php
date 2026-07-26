<?php

namespace App\Services\Company;

final class AnonymityThreshold
{
    private const DEFAULT = 5;

    public static function resolve(?int $configuredThreshold): int
    {
        return $configuredThreshold ?? self::DEFAULT;
    }
}
