<?php

namespace Database\Factories;

use App\Models\SystemExerciseTag;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemExerciseTagFactory extends Factory
{
    protected $model = SystemExerciseTag::class;

    public function definition(): array
    {
        return [
            'category' => SystemExerciseTag::CATEGORY_BODY_REGION,
            'key' => fake()->unique()->slug(2),
            'label' => fake()->word(),
            'description' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function goal(): static
    {
        return $this->state(fn () => [
            'category' => SystemExerciseTag::CATEGORY_GOAL,
        ]);
    }

    public function setting(): static
    {
        return $this->state(fn () => [
            'category' => SystemExerciseTag::CATEGORY_SETTING,
        ]);
    }

    public function equipment(): static
    {
        return $this->state(fn () => [
            'category' => SystemExerciseTag::CATEGORY_EQUIPMENT,
        ]);
    }
}
