<?php

namespace Database\Factories;

use App\Models\UserSystemMeasureExercise;
use App\Models\UserSystemMeasureExerciseCompletion;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserSystemMeasureExerciseCompletionFactory extends Factory
{
    protected $model = UserSystemMeasureExerciseCompletion::class;

    public function definition(): array
    {
        return [
            'user_system_measure_exercise_id' => UserSystemMeasureExercise::factory(),
            'completed_at' => now(),
            'period_key' => now()->format('Y-m-d'),
            'feedback_text' => fake()->sentence(),
            'effort_rating' => fake()->numberBetween(1, 5),
            'difficulty_rating' => fake()->numberBetween(1, 5),
            'pain_before_rating' => null,
            'pain_after_rating' => null,
            'stress_before_rating' => null,
            'stress_after_rating' => null,
            'points_awarded' => null,
            'points_transaction_id' => null,
        ];
    }
}
