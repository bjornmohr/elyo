<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'company_id' => Company::factory(),
            'status' => 'active',
        ];
    }

    public function platformAdmin(): static
    {
        return $this->state(fn () => [
            'company_id' => Company::query()->firstOrCreate(
                ['slug' => 'elyo-platform'],
                [
                    'name' => 'ELYO Platform',
                    'status' => 'active',
                    'anonymity_threshold' => 5,
                    'team_layer_enabled' => false,
                ],
            )->id,
        ]);
    }
}
