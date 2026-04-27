<?php

namespace Database\Factories;

use App\Models\Survey;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SurveyFactory extends Factory
{
    protected $model = Survey::class;

    public function definition(): array
    {
        return [
            'id' => Str::orderedUuid()->toString(),
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'company_id' => Company::factory(),
            'status' => 'ACTIVE',
            'is_anonymous' => true,
        ];
    }
}
