<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SurveyResponseFactory extends Factory
{
    protected $model = SurveyResponse::class;

    public function definition(): array
    {
        return [
            'survey_id' => Survey::factory(),
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
        ];
    }
}
