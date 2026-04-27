<?php

namespace Database\Factories;

use App\Models\WellbeingEntry;
use App\Models\User;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WellbeingEntryFactory extends Factory
{
    protected $model = WellbeingEntry::class;

    public function definition(): array
    {
        $mood = $this->faker->numberBetween(1, 10);
        $stress = $this->faker->numberBetween(1, 10);
        $energy = $this->faker->numberBetween(1, 10);

        return [
            'id' => Str::orderedUuid()->toString(),
            'mood' => $mood,
            'stress' => $stress,
            'energy' => $energy,
            'score' => ($mood + (11 - $stress) + $energy) / 3,
            'note' => $this->faker->sentence(),
            'period_key' => now()->format('Y-W'),
            'user_id' => User::factory(),
            'company_id' => function (array $attributes) {
                return User::find($attributes['user_id'])->company_id;
            },
        ];
    }
}
