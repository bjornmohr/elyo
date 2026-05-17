<?php

namespace Database\Seeders;

use App\Models\PointSetting;
use App\Services\PointsService;
use Illuminate\Database\Seeder;

class PointSettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PointsService::DEFAULT_POINTS as $action => $points) {
            PointSetting::updateOrCreate(
                ['action' => $action],
                ['points' => $points]
            );
        }
    }
}
