<?php

namespace Database\Factories;

use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

class SurveyAnswerFactory extends Factory
{
    protected $model = SurveyAnswer::class;

    public function definition(): array
    {
        return [
            'response_id' => SurveyResponse::factory(),
            'question_id' => SurveyQuestion::factory(),
            'scale_value' => fake()->numberBetween(1, 10),
        ];
    }
}
