<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Company;
use App\Models\User;
use App\Models\Team;
use App\Models\WellbeingEntry;

class DatabaseMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_run_successfully(): void
    {
        // If RefreshDatabase trait is used, this test passing means migrations ran.
        $this->assertTrue(true);
    }

    public function test_can_create_core_models(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $team = Team::factory()->create(['company_id' => $company->id, 'manager_id' => $user->id]);
        $entry = WellbeingEntry::factory()->create(['user_id' => $user->id, 'company_id' => $company->id]);

        $this->assertDatabaseHas('companies', ['id' => $company->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('teams', ['id' => $team->id]);
        $this->assertDatabaseHas('wellbeing_entries', ['id' => $entry->id]);
    }
}
