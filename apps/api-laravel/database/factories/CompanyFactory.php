<?php

namespace Database\Factories;

use App\Models\Company;
use App\Enums\CheckinFrequency;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'id' => Str::orderedUuid()->toString(),
            'name' => $this->faker->company(),
            'slug' => $this->faker->unique()->slug(),
            'checkin_frequency' => CheckinFrequency::WEEKLY,
            'anonymity_threshold' => 5,
        ];
    }
}
