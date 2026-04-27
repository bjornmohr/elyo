<?php

namespace Database\Factories;

use App\Models\SurveyQuestion;
use App\Models\Survey;
use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SurveyQuestionFactory extends Factory
{
    protected $model = SurveyQuestion::class;

    public function definition(): array
    {
        return [
            'id' => Str::orderedUuid()->toString(),
            'survey_id' => Survey::factory(),
            'text' => fake()->sentence(),
            'type' => QuestionType::SCALE,
            'order' => 0,
            'is_required' => true,
        ];
    }
}
