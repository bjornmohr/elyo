<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Survey;
use Illuminate\Database\Eloquent\Factories\Factory;

class SurveyFactory extends Factory
{
    protected $model = Survey::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'company_id' => Company::factory(),
            'status' => 'ACTIVE',
            'is_anonymous' => true,
        ];
    }
}
