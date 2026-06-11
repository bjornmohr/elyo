<?php

namespace Database\Factories;

use App\Models\SystemExercise;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SystemExerciseFactory extends Factory
{
    protected $model = SystemExercise::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'slug' => Str::slug($title),
            'title' => $title,
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'exercise_type' => SystemExercise::TYPE_MOBILITY,
            'difficulty' => SystemExercise::DIFFICULTY_BEGINNER,
            'default_duration_minutes' => fake()->randomElement([5, 10, 15, 20, 30]),
            'default_sets' => null,
            'default_repetitions' => null,
            'default_hold_seconds' => null,
            'instructions' => fake()->paragraph(),
            'safety_notes' => null,
            'contraindications' => null,
            'default_feedback_prompt' => null,
            'requires_feedback' => true,
            'status' => SystemExercise::STATUS_ACTIVE,
            'created_by_user_id' => null,
        ];
    }

    public function strength(): static
    {
        return $this->state(fn () => [
            'exercise_type' => SystemExercise::TYPE_STRENGTH,
            'default_sets' => 3,
            'default_repetitions' => 10,
        ]);
    }

    public function breathing(): static
    {
        return $this->state(fn () => [
            'exercise_type' => SystemExercise::TYPE_BREATHING,
        ]);
    }

    public function education(): static
    {
        return $this->state(fn () => [
            'exercise_type' => SystemExercise::TYPE_EDUCATION,
            'default_duration_minutes' => null,
            'default_sets' => null,
            'default_repetitions' => null,
        ]);
    }

    public function reflection(): static
    {
        return $this->state(fn () => [
            'exercise_type' => SystemExercise::TYPE_REFLECTION,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => SystemExercise::STATUS_DRAFT,
        ]);
    }

    public function advanced(): static
    {
        return $this->state(fn () => [
            'difficulty' => SystemExercise::DIFFICULTY_ADVANCED,
        ]);
    }
}
