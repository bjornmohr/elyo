<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use App\Models\UserSystemMeasure;
use App\Models\UserSystemMeasureExercise;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserSystemMeasureFactory extends Factory
{
    protected $model = UserSystemMeasure::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_id' => fn (array $attributes): int => is_numeric($attributes['user_id'])
                ? (int) (User::find($attributes['user_id'])?->company_id ?? Company::factory()->create()->id)
                : Company::factory()->create()->id,
            'source_system_measure_template_id' => null,
            'assigned_by_user_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'assignment_reason' => null,
            'recommendation_context' => null,
            'status' => UserSystemMeasure::STATUS_ASSIGNED,
            'starts_at' => null,
            'ends_at' => null,
            'assigned_at' => now(),
            'completed_at' => null,
            'streak_enabled' => true,
            'points_enabled' => true,
            'points_per_completion' => null,
            'requires_feedback' => true,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => UserSystemMeasure::STATUS_ACTIVE,
            'starts_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => UserSystemMeasure::STATUS_COMPLETED,
            'starts_at' => now()->subDays(7),
            'completed_at' => now(),
        ]);
    }

    public function fromTemplate(): static
    {
        return $this->afterCreating(function (UserSystemMeasure $userMeasure) {
            $template = \App\Models\SystemMeasureTemplate::factory()->withExercises(3)->create();
            $userMeasure->update([
                'source_system_measure_template_id' => $template->id,
                'title' => $template->title,
                'description' => $template->description,
            ]);

            foreach ($template->templateExercises()->with('exercise')->orderBy('position')->get() as $te) {
                $exercise = $te->exercise;
                UserSystemMeasureExercise::create([
                    'user_system_measure_id' => $userMeasure->id,
                    'source_system_exercise_id' => $exercise->id,
                    'position' => $te->position,
                    'title' => $te->custom_title ?? $exercise->title,
                    'short_description' => $exercise->short_description,
                    'description' => $exercise->description,
                    'exercise_type' => $exercise->exercise_type,
                    'difficulty' => $exercise->difficulty,
                    'duration_minutes' => $te->custom_duration_minutes ?? $exercise->default_duration_minutes,
                    'sets' => $te->custom_sets ?? $exercise->default_sets,
                    'repetitions' => $te->custom_repetitions ?? $exercise->default_repetitions,
                    'hold_seconds' => $te->custom_hold_seconds ?? $exercise->default_hold_seconds,
                    'instructions' => $te->custom_instructions ?? $exercise->instructions,
                    'safety_notes' => $exercise->safety_notes,
                    'contraindications' => $exercise->contraindications,
                    'feedback_prompt' => $te->custom_feedback_prompt ?? $exercise->default_feedback_prompt,
                    'requires_feedback' => $exercise->requires_feedback,
                    'status' => UserSystemMeasureExercise::STATUS_PENDING,
                ]);
            }
        });
    }
}
