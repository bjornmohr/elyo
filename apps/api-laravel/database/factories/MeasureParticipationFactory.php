<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Measure;
use App\Models\MeasureParticipation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeasureParticipationFactory extends Factory
{
    protected $model = MeasureParticipation::class;

    public function definition(): array
    {
        return [
            'measure_id' => Measure::factory(),
            'user_id' => User::factory(),
            'company_id' => Company::factory(),
            'team_id' => null,
            'participated_at' => now(),
        ];
    }
}
