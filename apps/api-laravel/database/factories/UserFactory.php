<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Company;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'id' => Str::orderedUuid()->toString(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'role' => Role::EMPLOYEE,
            'password_hash' => Hash::make('password'),
            'is_active' => true,
            'company_id' => Company::factory(),
        ];
    }
}
