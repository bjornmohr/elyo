<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $testCompany = Company::query()->firstOrCreate(
            ['slug' => 'test-company'],
            [
                'name' => 'Test Company',
                'status' => 'active',
                'anonymity_threshold' => 5,
                'team_layer_enabled' => true,
            ],
        );

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'company_id' => $testCompany->id,
        ]);

        $this->call(PointSettingsSeeder::class);
        $this->call(SystemExerciseSeeder::class);
        $this->call(DemoDataSeeder::class);
    }
}
