<?php

namespace Database\Factories;

use App\Models\SystemExercise;
use App\Models\SystemMeasureTemplate;
use App\Models\SystemMeasureTemplateExercise;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemMeasureTemplateExerciseFactory extends Factory
{
    protected $model = SystemMeasureTemplateExercise::class;

    public function definition(): array
    {
        return [
            'system_measure_template_id' => SystemMeasureTemplate::factory(),
            'system_exercise_id' => SystemExercise::factory(),
            'position' => fake()->unique()->numberBetween(1, 10000),
            'is_required' => true,
            'custom_title' => null,
            'custom_instructions' => null,
            'custom_duration_minutes' => null,
            'custom_sets' => null,
            'custom_repetitions' => null,
            'custom_hold_seconds' => null,
            'custom_feedback_prompt' => null,
        ];
    }
}
