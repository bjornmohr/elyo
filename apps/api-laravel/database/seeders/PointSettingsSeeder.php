<?php

namespace Database\Seeders;

use App\Models\PointSetting;
use App\Services\PointSettingsService;
use Illuminate\Database\Seeder;

class PointSettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PointSettingsService::DEFAULT_POINTS as $action => $points) {
            PointSetting::updateOrCreate(
                ['action' => $action],
                ['points' => $points]
            );
        }
    }
}
