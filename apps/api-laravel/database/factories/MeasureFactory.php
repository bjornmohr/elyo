<?php

namespace Database\Factories;

use App\Models\Measure;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MeasureFactory extends Factory
{
    protected $model = Measure::class;

    public function definition(): array
    {
        return [
            'id' => Str::orderedUuid()->toString(),
            'company_id' => Company::factory(),
            'title' => fake()->sentence(),
            'category' => 'workshop',
            'description' => fake()->paragraph(),
            'status' => 'SUGGESTED',
            'created_by' => User::factory(),
        ];
    }
}
