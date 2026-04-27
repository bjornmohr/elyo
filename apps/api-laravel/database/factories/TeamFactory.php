<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'id' => Str::orderedUuid()->toString(),
            'name' => $this->faker->word(),
            'company_id' => Company::factory(),
            'manager_id' => User::factory(),
        ];
    }
}
