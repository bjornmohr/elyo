<?php

namespace Database\Factories;

use App\Models\SystemExercise;
use App\Models\SystemMeasureTemplate;
use App\Models\SystemMeasureTemplateExercise;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SystemMeasureTemplateFactory extends Factory
{
    protected $model = SystemMeasureTemplate::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'slug' => Str::slug($title),
            'title' => $title,
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'goal_summary' => null,
            'difficulty' => SystemMeasureTemplate::DIFFICULTY_BEGINNER,
            'estimated_duration_minutes' => fake()->randomElement([15, 30, 45, 60]),
            'recommended_frequency' => SystemMeasureTemplate::FREQUENCY_DAILY,
            'default_points' => fake()->randomElement([5, 10, 20]),
            'streak_enabled' => true,
            'requires_feedback' => true,
            'status' => SystemMeasureTemplate::STATUS_ACTIVE,
            'created_by_user_id' => null,
        ];
    }

    public function withExercises(int $count = 3): static
    {
        return $this->afterCreating(function (SystemMeasureTemplate $template) use ($count) {
            for ($i = 1; $i <= $count; $i++) {
                $exercise = SystemExercise::factory()->create();
                SystemMeasureTemplateExercise::create([
                    'system_measure_template_id' => $template->id,
                    'system_exercise_id' => $exercise->id,
                    'position' => $i,
                ]);
            }
        });
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => SystemMeasureTemplate::STATUS_DRAFT,
        ]);
    }
}
