<?php

namespace Database\Factories;

use App\Models\SystemExercise;
use App\Models\UserSystemMeasure;
use App\Models\UserSystemMeasureExercise;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserSystemMeasureExerciseFactory extends Factory
{
    protected $model = UserSystemMeasureExercise::class;

    public function definition(): array
    {
        return [
            'user_system_measure_id' => UserSystemMeasure::factory(),
            'source_system_exercise_id' => null,
            'position' => 1,
            'title' => fake()->sentence(3),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'exercise_type' => SystemExercise::TYPE_MOBILITY,
            'difficulty' => SystemExercise::DIFFICULTY_BEGINNER,
            'duration_minutes' => fake()->randomElement([5, 10, 15]),
            'sets' => null,
            'repetitions' => null,
            'hold_seconds' => null,
            'instructions' => fake()->paragraph(),
            'safety_notes' => null,
            'contraindications' => null,
            'feedback_prompt' => null,
            'requires_feedback' => true,
            'tag_snapshot' => null,
            'status' => UserSystemMeasureExercise::STATUS_PENDING,
        ];
    }
}
