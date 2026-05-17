<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Measure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeasureFactory extends Factory
{
    protected $model = Measure::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => fake()->sentence(),
            'category' => 'workshop',
            'description' => fake()->paragraph(),
            'status' => 'SUGGESTED',
            'created_by' => User::factory(),
        ];
    }
}
