<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Measure;
use App\Models\MeasureParticipation;
use App\Models\Team;
use App\Models\User;
use App\Models\UserRole;
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

    public function test_summary_excludes_report_viewer_participation_from_counts(): void
    {
        $company = Company::factory()->create(['anonymity_threshold' => 3]);
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);
        $measure = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => null,
            'created_by' => $admin->id,
        ]);
        $employees = User::factory()->count(3)->create([
            'company_id' => $company->id,
            'role' => Role::EMPLOYEE,
        ]);
        $reportViewer = User::factory()->create([
            'company_id' => $company->id,
            'role' => Role::EMPLOYEE,
        ]);
        UserRole::create(['user_id' => $reportViewer->id, 'role' => Role::COMPANY_MANAGER]);

        $employees->push($reportViewer)->each(fn (User $participant) => MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $participant->id,
            'company_id' => $company->id,
            'team_id' => $participant->team_id,
        ]));

        // The report viewer participates personally but must not appear in the
        // eligible denominator or the participant numerator.
        $this->actingAs($admin)
            ->getJson("/api/company/measures/{$measure->id}/participation-summary")
            ->assertOk()
            ->assertJsonPath('data.isAboveThreshold', true)
            ->assertJsonPath('data.eligibleCount', 3)
            ->assertJsonPath('data.participantCount', 3)
            ->assertJsonPath('data.participationRate', 100);
    }

    public function test_summary_stays_suppressed_when_only_report_viewer_participation_reaches_threshold(): void
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
        $reportViewer = User::factory()->create([
            'company_id' => $company->id,
            'role' => Role::EMPLOYEE,
        ]);
        UserRole::create(['user_id' => $reportViewer->id, 'role' => Role::COMPANY_ADMIN]);

        // 2 reportable participants + 1 report viewer: raw participation
        // reaches the threshold of 3, but the summary must stay suppressed.
        $employees->take(2)->push($reportViewer)->each(fn (User $participant) => MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $participant->id,
            'company_id' => $company->id,
            'team_id' => $participant->team_id,
        ]));

        $this->actingAs($admin)
            ->getJson("/api/company/measures/{$measure->id}/participation-summary")
            ->assertOk()
            ->assertJsonPath('data.isAboveThreshold', false)
            ->assertJsonPath('data.eligibleCount', null)
            ->assertJsonPath('data.participantCount', null)
            ->assertJsonPath('data.suppressionReason', 'ANONYMITY_THRESHOLD_NOT_MET');
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

    public function test_manager_summary_counts_participants_through_current_managed_team_scope(): void
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
        $currentManagedEmployees = User::factory()->count(3)->create([
            'company_id' => $company->id,
            'team_id' => $managedTeam->id,
            'role' => Role::EMPLOYEE,
        ]);
        $transferredEmployees = User::factory()->count(2)->create([
            'company_id' => $company->id,
            'team_id' => $managedTeam->id,
            'role' => Role::EMPLOYEE,
        ]);

        $currentManagedEmployees->each(fn (User $employee) => MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $employee->id,
            'company_id' => $company->id,
            'team_id' => $managedTeam->id,
        ]));
        $transferredEmployees->each(function (User $employee) use ($measure, $company, $managedTeam, $otherTeam): void {
            MeasureParticipation::factory()->create([
                'measure_id' => $measure->id,
                'user_id' => $employee->id,
                'company_id' => $company->id,
                'team_id' => $managedTeam->id,
            ]);

            $employee->update(['team_id' => $otherTeam->id]);
        });

        $response = $this->actingAs($manager)
            ->getJson("/api/company/measures/{$measure->id}/participation-summary");

        $response->assertOk()
            ->assertJsonPath('data.isAboveThreshold', true)
            ->assertJsonPath('data.eligibleCount', 3)
            ->assertJsonPath('data.participantCount', 3)
            ->assertJsonPath('data.participationRate', 100)
            ->assertJsonPath('data.teamBreakdown', null);

        $this->assertLessThanOrEqual(100, $response->json('data.participationRate'));
        $this->assertNoIndividualParticipationData($response->getContent());
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

    public function test_team_specific_measure_summary_is_blocked_when_team_layer_is_disabled(): void
    {
        $company = Company::factory()->create(['team_layer_enabled' => false]);
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);
        $team = Team::factory()->create(['company_id' => $company->id]);
        $measure = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => $team->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->getJson("/api/company/measures/{$measure->id}/participation-summary")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'TEAM_LAYER_DISABLED');
    }

    public function test_manager_without_managed_team_cannot_fetch_measure_participation_summary(): void
    {
        $company = Company::factory()->create(['team_layer_enabled' => true]);
        $manager = $this->companyUser($company, Role::COMPANY_MANAGER);
        $measure = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => null,
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

    public function test_qr_participations_remain_aggregate_only_in_summary(): void
    {
        $company = Company::factory()->create(['anonymity_threshold' => 3]);
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);
        $measure = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => null,
            'created_by' => $admin->id,
            'verification_requirement' => 'QR_CODE',
        ]);
        $employees = User::factory()->count(5)->create([
            'company_id' => $company->id,
            'role' => Role::EMPLOYEE,
        ]);

        $employees->take(4)->each(fn (User $employee) => MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $employee->id,
            'company_id' => $company->id,
            'team_id' => null,
            'verification_type' => MeasureParticipation::VERIFICATION_TYPE_QR_CHECKIN,
        ]));

        $response = $this->actingAs($admin)
            ->getJson("/api/company/measures/{$measure->id}/participation-summary");

        $response->assertOk()
            ->assertJsonPath('data.measureId', $measure->id)
            ->assertJsonPath('data.isAboveThreshold', true)
            ->assertJsonPath('data.eligibleCount', 5)
            ->assertJsonPath('data.participantCount', 4)
            ->assertJsonPath('data.participationRate', 80)
            ->assertJsonPath('data.suppressionReason', null);

        $this->assertNoIndividualParticipationData($response->getContent());
    }

    public function test_summary_does_not_count_reportable_employee_from_different_company(): void
    {
        $company = Company::factory()->create(['anonymity_threshold' => 3]);
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);
        $measure = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => null,
            'created_by' => $admin->id,
        ]);

        // 3 reportable employees in the measure's company — exactly at threshold.
        $employees = User::factory()->count(3)->create([
            'company_id' => $company->id,
            'role' => Role::EMPLOYEE,
        ]);
        $employees->each(fn (User $emp) => MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $emp->id,
            'company_id' => $company->id,
            'team_id' => $emp->team_id,
        ]));

        // A pure EMPLOYEE from another company also participates — must be excluded.
        $otherCompany = Company::factory()->create();
        $otherEmployee = User::factory()->create([
            'company_id' => $otherCompany->id,
            'role' => Role::EMPLOYEE,
        ]);
        MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $otherEmployee->id,
            'company_id' => $otherCompany->id,
        ]);

        // Eligible and participant counts must reflect only the 3 in-company employees.
        $this->actingAs($admin)
            ->getJson("/api/company/measures/{$measure->id}/participation-summary")
            ->assertOk()
            ->assertJsonPath('data.isAboveThreshold', true)
            ->assertJsonPath('data.eligibleCount', 3)
            ->assertJsonPath('data.participantCount', 3);
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
        foreach ([
            'user_id',
            'userId',
            'name',
            'email',
            'participated_at',
            'participatedAt',
            'verificationType',
            'verification_type',
            'verifiedAt',
            'verified_at',
            'verifiedBy',
            'verified_by_user_id',
            'participations',
        ] as $field) {
            $this->assertStringNotContainsString($field, $content);
        }
    }
}
