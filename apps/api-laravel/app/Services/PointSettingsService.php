<?php

namespace App\Services;

use App\Models\PointSetting;

class PointSettingsService
{
    public const DEFAULT_POINTS = [
        'daily_checkin' => 10,
        'anamnesis_completed' => 100,
        'medical_document_upload' => 25,
        'measure_participation' => 20,
        'streak_7days' => 50,
        'streak_30days' => 200,
    ];

    public static function resolvePointMap(): array
    {
        $configured = PointSetting::query()
            ->whereIn('action', array_keys(self::DEFAULT_POINTS))
            ->pluck('points', 'action')
            ->all();

        return array_merge(self::DEFAULT_POINTS, $configured);
    }
}
