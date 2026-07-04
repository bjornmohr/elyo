<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Measure;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeasureImpactAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_access_any_own_company_measure_impact(): void
    {
        $company = Company::factory()->create(['team_layer_enabled' => true]);
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);
        $team = Team::factory()->create(['company_id' => $company->id]);
        $measure = $this->measure($company, $admin, ['team_id' => $team->id]);

        $this->actingAs($admin)
            ->getJson("/api/company/measures/{$measure->id}/impact")
            ->assertOk()
            ->assertJsonPath('data.measureId', $measure->id);
    }

    public function test_company_manager_can_access_company_wide_measure_impact(): void
    {
        $company = Company::factory()->create(['team_layer_enabled' => true]);
        $manager = $this->companyUser($company, Role::COMPANY_MANAGER);
        Team::factory()->create(['company_id' => $company->id, 'manager_id' => $manager->id]);
        $measure = $this->measure($company, $manager, ['team_id' => null]);

        $this->actingAs($manager)
            ->getJson("/api/company/measures/{$measure->id}/impact")
            ->assertOk()
            ->assertJsonPath('data.measureId', $measure->id);
    }

    public function test_company_manager_can_access_own_team_measure_impact(): void
    {
        $company = Company::factory()->create(['team_layer_enabled' => true]);
        $manager = $this->companyUser($company, Role::COMPANY_MANAGER);
        $team = Team::factory()->create(['company_id' => $company->id, 'manager_id' => $manager->id]);
        $measure = $this->measure($company, $manager, ['team_id' => $team->id]);

        $this->actingAs($manager)
            ->getJson("/api/company/measures/{$measure->id}/impact")
            ->assertOk()
            ->assertJsonPath('data.measureId', $measure->id);
    }

    public function test_company_manager_cannot_access_other_team_measure_impact(): void
    {
        $company = Company::factory()->create(['team_layer_enabled' => true]);
        $manager = $this->companyUser($company, Role::COMPANY_MANAGER);
        Team::factory()->create(['company_id' => $company->id, 'manager_id' => $manager->id]);
        $otherTeam = Team::factory()->create(['company_id' => $company->id]);
        $measure = $this->measure($company, $manager, ['team_id' => $otherTeam->id]);

        $this->actingAs($manager)
            ->getJson("/api/company/measures/{$measure->id}/impact")
            ->assertNotFound();
    }

    public function test_company_manager_cannot_access_team_measure_impact_when_team_layer_is_disabled(): void
    {
        $company = Company::factory()->create(['team_layer_enabled' => false]);
        $manager = $this->companyUser($company, Role::COMPANY_MANAGER);
        $team = Team::factory()->create(['company_id' => $company->id, 'manager_id' => $manager->id]);
        $measure = $this->measure($company, $manager, ['team_id' => $team->id]);

        $this->actingAs($manager)
            ->getJson("/api/company/measures/{$measure->id}/impact")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PORTAL_FORBIDDEN');
    }

    public function test_cross_company_measure_impact_access_is_blocked(): void
    {
        $company = Company::factory()->create(['team_layer_enabled' => true]);
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);
        $foreignCompany = Company::factory()->create(['team_layer_enabled' => true]);
        $foreignAdmin = $this->companyUser($foreignCompany, Role::COMPANY_ADMIN);
        $measure = $this->measure($foreignCompany, $foreignAdmin);

        $this->actingAs($admin)
            ->getJson("/api/company/measures/{$measure->id}/impact")
            ->assertNotFound();
    }

    private function companyUser(Company $company, Role $role): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
        ]);
    }

    private function measure(Company $company, User $creator, array $attributes = []): Measure
    {
        return Measure::factory()->create([
            'company_id' => $company->id,
            'created_by' => $creator->id,
            'status' => 'COMPLETED',
            ...$attributes,
        ]);
    }
}
