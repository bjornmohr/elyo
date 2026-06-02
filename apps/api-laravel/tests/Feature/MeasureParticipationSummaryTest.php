<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Measure;
use App\Models\MeasureParticipation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeasureParticipationSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_fetch_measure_participation_summary(): void
    {
        $company = Company::factory()->create(['anonymity_threshold' => 3]);
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);
        $measure = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => null,
            'created_by' => $admin->id,
        ]);
        $employees = User::factory()->count(5)->create([
            'company_id' => $company->id,
            'role' => Role::EMPLOYEE,
        ]);

        $employees->take(3)->each(fn (User $employee) => MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $employee->id,
            'company_id' => $company->id,
            'team_id' => $employee->team_id,
        ]));

        MeasureParticipation::factory()->create();
        MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'company_id' => Company::factory()->create()->id,
        ]);

        $response = $this->actingAs($admin)->getJson("/api/company/measures/{$measure->id}/participation-summary");

        $response->assertOk()
            ->assertJsonPath('data.measureId', $measure->id)
            ->assertJsonPath('data.isAboveThreshold', true)
            ->assertJsonPath('data.eligibleCount', 5)
            ->assertJsonPath('data.participantCount', 3)
            ->assertJsonPath('data.participationRate', 60)
            ->assertJsonPath('data.suppressionReason', null)
            ->assertJsonPath('data.teamBreakdown', null);

        $this->assertNoIndividualParticipationData($response->getContent());
    }

    public function test_summary_suppresses_counts_when_anonymity_threshold_is_not_met(): void
    {
        $company = Company::factory()->create(['anonymity_threshold' => 3]);
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);
        $measure = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => null,
            'created_by' => $admin->id,
        ]);
        $employees = User::factory()->count(5)->create([
            'company_id' => $company->id,
            'role' => Role::EMPLOYEE,
        ]);

        $employees->take(2)->each(fn (User $employee) => MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $employee->id,
            'company_id' => $company->id,
            'team_id' => $employee->team_id,
        ]));

        $this->actingAs($admin)
            ->getJson("/api/company/measures/{$measure->id}/participation-summary")
            ->assertOk()
            ->assertJsonPath('data.isAboveThreshold', false)
            ->assertJsonPath('data.eligibleCount', null)
            ->assertJsonPath('data.participantCount', null)
            ->assertJsonPath('data.participationRate', null)
            ->assertJsonPath('data.suppressionReason', 'ANONYMITY_THRESHOLD_NOT_MET')
            ->assertJsonPath('data.teamBreakdown', null);
    }

    public function test_company_admin_cannot_fetch_foreign_company_measure_summary(): void
    {
        $company = Company::factory()->create();
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);
        $foreignMeasure = Measure::factory()->create([
            'company_id' => Company::factory()->create()->id,
        ]);

        $this->actingAs($admin)
            ->getJson("/api/company/measures/{$foreignMeasure->id}/participation-summary")
            ->assertNotFound();
    }

    public function test_team_specific_summary_counts_only_target_team_scope(): void
    {
        $company = Company::factory()->create([
            'anonymity_threshold' => 3,
            'team_layer_enabled' => true,
        ]);
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);
        $team = Team::factory()->create(['company_id' => $company->id]);
        $otherTeam = Team::factory()->create(['company_id' => $company->id]);
        $measure = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => $team->id,
            'created_by' => $admin->id,
        ]);
        $teamEmployees = User::factory()->count(4)->create([
            'company_id' => $company->id,
            'team_id' => $team->id,
            'role' => Role::EMPLOYEE,
        ]);
        $otherTeamEmployees = User::factory()->count(4)->create([
            'company_id' => $company->id,
            'team_id' => $otherTeam->id,
            'role' => Role::EMPLOYEE,
        ]);

        $teamEmployees->take(3)->each(fn (User $employee) => MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $employee->id,
            'company_id' => $company->id,
            'team_id' => $team->id,
        ]));
        $otherTeamEmployees->take(3)->each(fn (User $employee) => MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $employee->id,
            'company_id' => $company->id,
            'team_id' => $otherTeam->id,
        ]));

        $this->actingAs($admin)
            ->getJson("/api/company/measures/{$measure->id}/participation-summary")
            ->assertOk()
            ->assertJsonPath('data.isAboveThreshold', true)
            ->assertJsonPath('data.eligibleCount', 4)
            ->assertJsonPath('data.participantCount', 3)
            ->assertJsonPath('data.participationRate', 75);
    }

    public function test_manager_summary_for_company_wide_measure_is_scoped_to_managed_team(): void
    {
        $company = Company::factory()->create([
            'anonymity_threshold' => 3,
            'team_layer_enabled' => true,
        ]);
        $manager = $this->companyUser($company, Role::COMPANY_MANAGER);
        $managedTeam = Team::factory()->create([
            'company_id' => $company->id,
            'manager_id' => $manager->id,
        ]);
        $otherTeam = Team::factory()->create(['company_id' => $company->id]);
        $measure = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => null,
        ]);
        $managedEmployees = User::factory()->count(5)->create([
            'company_id' => $company->id,
            'team_id' => $managedTeam->id,
            'role' => Role::EMPLOYEE,
        ]);
        $otherEmployees = User::factory()->count(5)->create([
            'company_id' => $company->id,
            'team_id' => $otherTeam->id,
            'role' => Role::EMPLOYEE,
        ]);

        $managedEmployees->take(3)->each(fn (User $employee) => MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $employee->id,
            'company_id' => $company->id,
            'team_id' => $managedTeam->id,
        ]));
        $otherEmployees->each(fn (User $employee) => MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $employee->id,
            'company_id' => $company->id,
            'team_id' => $otherTeam->id,
        ]));

        $this->actingAs($manager)
            ->getJson("/api/company/measures/{$measure->id}/participation-summary")
            ->assertOk()
            ->assertJsonPath('data.isAboveThreshold', true)
            ->assertJsonPath('data.eligibleCount', 5)
            ->assertJsonPath('data.participantCount', 3)
            ->assertJsonPath('data.participationRate', 60);
    }

    public function test_manager_cannot_fetch_summary_for_other_team_measure(): void
    {
        $company = Company::factory()->create(['team_layer_enabled' => true]);
        $manager = $this->companyUser($company, Role::COMPANY_MANAGER);
        Team::factory()->create([
            'company_id' => $company->id,
            'manager_id' => $manager->id,
        ]);
        $otherTeam = Team::factory()->create(['company_id' => $company->id]);
        $measure = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => $otherTeam->id,
        ]);

        $this->actingAs($manager)
            ->getJson("/api/company/measures/{$measure->id}/participation-summary")
            ->assertForbidden();
    }

    public function test_employee_cannot_access_company_measure_participation_summary(): void
    {
        $company = Company::factory()->create();
        $employee = $this->companyUser($company, Role::EMPLOYEE);
        $measure = Measure::factory()->create(['company_id' => $company->id]);

        $this->actingAs($employee)
            ->getJson("/api/company/measures/{$measure->id}/participation-summary")
            ->assertForbidden();
    }

    private function companyUser(Company $company, Role $role): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
        ]);
    }

    private function assertNoIndividualParticipationData(string $content): void
    {
        foreach (['user_id', 'userId', 'name', 'email', 'participated_at', 'participatedAt', 'participations'] as $field) {
            $this->assertStringNotContainsString($field, $content);
        }
    }
}
