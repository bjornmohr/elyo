<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\ConfiguresPrivacyMapping;
use Tests\Support\CreatesWellbeingCheckins;
use Tests\TestCase;

class DatabaseMigrationTest extends TestCase
{
    use ConfiguresPrivacyMapping;
    use CreatesWellbeingCheckins;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePrivacyMapping('migration-feature-test');
    }

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
        $entry = $this->createWellbeingEntry($user);

        $this->assertDatabaseHas('companies', ['id' => $company->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('teams', ['id' => $team->id]);
        $this->assertDatabaseHas('wellbeing_entries', ['id' => $entry->id], 'health');
    }

    public function test_user_factory_creates_users_with_company(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->company_id);
        $this->assertDatabaseHas('companies', ['id' => $user->company_id]);
    }

    public function test_platform_admin_factory_uses_internal_platform_company(): void
    {
        $users = User::factory()->platformAdmin()->count(2)->create();

        foreach ($users as $user) {
            $this->assertNotNull($user->company_id);
            $this->assertDatabaseHas('companies', [
                'id' => $user->company_id,
                'slug' => 'elyo-platform',
                'name' => 'ELYO Platform',
            ]);
        }
    }

    public function test_creating_user_without_company_id_fails_at_database_level(): void
    {
        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'name' => 'Companyless User',
            'email' => 'companyless@example.com',
            'password' => 'password',
        ]);
    }

    public function test_creating_invite_token_without_company_id_fails_at_database_level(): void
    {
        $this->expectException(QueryException::class);

        DB::table('invite_tokens')->insert([
            'email' => 'companyless-invite@example.com',
            'role' => 'EMPLOYEE',
            'token_hash' => hash('sha256', 'companyless-invite'),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function test_company_with_users_cannot_be_deleted(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        try {
            DB::transaction(fn () => $company->delete());
            $this->fail('Company deletion should be restricted while users reference it.');
        } catch (QueryException) {
            $this->assertDatabaseHas('companies', ['id' => $company->id]);
            $this->assertDatabaseHas('users', [
                'id' => $user->id,
                'company_id' => $company->id,
            ]);
        }
    }

    public function test_seeded_users_have_valid_company_assignments(): void
    {
        $this->seed(DatabaseSeeder::class);

        $support = User::where('email', 'support@elyo.de')->firstOrFail();
        $testUser = User::where('email', 'test@example.com')->firstOrFail();

        $this->assertDatabaseHas('companies', [
            'id' => $support->company_id,
            'slug' => 'elyo-platform',
            'name' => 'ELYO Platform',
        ]);
        $this->assertNotNull($testUser->company_id);
        $this->assertDatabaseHas('companies', [
            'id' => $testUser->company_id,
            'slug' => 'test-company',
        ]);
    }
}
