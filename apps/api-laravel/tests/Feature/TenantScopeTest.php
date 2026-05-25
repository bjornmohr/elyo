<?php

namespace Tests\Feature;

use App\Enums\QuestionType;
use App\Enums\Role;
use App\Enums\SurveyStatus;
use App\Models\Company;
use App\Models\InviteToken;
use App\Models\Measure;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\Team;
use App\Models\User;
use App\Models\WellbeingEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_forged_user_and_company_ids_are_ignored_on_checkin(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $employee = User::factory()->create(['company_id' => $company->id, 'role' => Role::EMPLOYEE]);
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id, 'role' => Role::EMPLOYEE]);

        $this->actingAs($employee, 'sanctum')->postJson('/api/employee/checkin', [
            'mood' => 8,
            'stress' => 3,
            'energy' => 7,
            'user_id' => $otherUser->id,
            'userId' => $otherUser->id,
            'company_id' => $otherCompany->id,
            'companyId' => $otherCompany->id,
            'role' => Role::COMPANY_ADMIN->value,
        ])->assertStatus(200);

        $this->assertDatabaseHas('wellbeing_entries', [
            'user_id' => $employee->id,
            'company_id' => $company->id,
        ]);
        $this->assertDatabaseMissing('wellbeing_entries', [
            'user_id' => $otherUser->id,
            'company_id' => $otherCompany->id,
        ]);
    }

    public function test_employee_forged_user_id_is_ignored_on_profile_update(): void
    {
        $company = Company::factory()->create();
        $employee = User::factory()->create(['company_id' => $company->id, 'role' => Role::EMPLOYEE, 'name' => 'Own Name']);
        $otherUser = User::factory()->create(['company_id' => $company->id, 'role' => Role::EMPLOYEE, 'name' => 'Other Name']);

        $this->actingAs($employee, 'sanctum')->putJson('/api/employee/profile', [
            'user_id' => $otherUser->id,
            'userId' => $otherUser->id,
            'company_id' => $company->id,
            'companyId' => $company->id,
            'role' => Role::COMPANY_ADMIN->value,
            'name' => 'Updated Own Name',
        ])->assertStatus(200);

        $this->assertSame('Updated Own Name', $employee->refresh()->name);
        $this->assertSame('Other Name', $otherUser->refresh()->name);
    }

    public function test_employee_cannot_access_or_respond_to_other_team_survey(): void
    {
        $company = Company::factory()->create();
        $ownTeam = Team::factory()->create(['company_id' => $company->id]);
        $otherTeam = Team::factory()->create(['company_id' => $company->id]);
        $employee = User::factory()->create([
            'company_id' => $company->id,
            'team_id' => $ownTeam->id,
            'role' => Role::EMPLOYEE,
        ]);
        $survey = Survey::factory()->create(['company_id' => $company->id, 'status' => SurveyStatus::ACTIVE]);
        $survey->teams()->sync([$otherTeam->id]);
        $question = SurveyQuestion::factory()->create([
            'survey_id' => $survey->id,
            'type' => QuestionType::SCALE,
        ]);

        $this->actingAs($employee, 'sanctum')
            ->getJson("/api/employee/surveys/{$survey->id}")
            ->assertStatus(404);

        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/employee/surveys/{$survey->id}/respond", [
                'company_id' => $company->id,
                'team_id' => $otherTeam->id,
                'answers' => [
                    ['questionId' => $question->id, 'scaleValue' => 8],
                ],
            ])
            ->assertStatus(404);
    }

    public function test_company_admin_cannot_use_foreign_team_id_for_measure_or_report(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => Role::COMPANY_ADMIN]);
        $foreignTeam = Team::factory()->create(['company_id' => $otherCompany->id]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/company/measures', [
            'title' => 'Foreign team measure',
            'category' => 'sport',
            'description' => 'A measure with a forged foreign team.',
            'teamId' => $foreignTeam->id,
            'company_id' => $otherCompany->id,
        ])->assertStatus(422);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/company/reports?teamId={$foreignTeam->id}")
            ->assertStatus(403);
    }

    public function test_company_admin_cannot_access_foreign_company_resources_by_route_id(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => Role::COMPANY_ADMIN]);
        $foreignTeam = Team::factory()->create(['company_id' => $otherCompany->id]);
        $foreignMeasure = Measure::factory()->create(['company_id' => $otherCompany->id, 'team_id' => $foreignTeam->id]);
        $foreignSurvey = Survey::factory()->create(['company_id' => $otherCompany->id]);

        $this->actingAs($admin, 'sanctum')->getJson("/api/company/teams/{$foreignTeam->id}")->assertStatus(404);
        $this->actingAs($admin, 'sanctum')->patchJson("/api/company/measures/{$foreignMeasure->id}", [
            'status' => 'COMPLETED',
        ])->assertStatus(404);
        $this->actingAs($admin, 'sanctum')->getJson("/api/company/surveys/{$foreignSurvey->id}")->assertStatus(404);
    }

    public function test_company_manager_cannot_access_non_managed_team_or_create_out_of_scope_resources(): void
    {
        $company = Company::factory()->create();
        $managedTeam = Team::factory()->create(['company_id' => $company->id]);
        $otherTeam = Team::factory()->create(['company_id' => $company->id]);
        $manager = User::factory()->create(['company_id' => $company->id, 'role' => Role::COMPANY_MANAGER]);
        $managedTeam->update(['manager_id' => $manager->id]);

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/company/teams/{$otherTeam->id}")
            ->assertStatus(403);

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/company/teams/{$otherTeam->id}/members")
            ->assertStatus(403);

        $this->actingAs($manager, 'sanctum')->postJson('/api/company/surveys', [
            'title' => 'Forged team survey',
            'teamIds' => [$otherTeam->id],
            'questions' => [
                ['text' => 'Question?', 'type' => 'SCALE', 'order' => 0],
            ],
        ])->assertStatus(403);

        $this->actingAs($manager, 'sanctum')->postJson('/api/company/invitations', [
            'email' => 'employee@example.com',
            'role' => Role::EMPLOYEE->value,
        ])->assertStatus(403);
    }

    public function test_company_manager_users_are_limited_to_managed_team(): void
    {
        $company = Company::factory()->create();
        $managedTeam = Team::factory()->create(['company_id' => $company->id]);
        $otherTeam = Team::factory()->create(['company_id' => $company->id]);
        $manager = User::factory()->create(['company_id' => $company->id, 'role' => Role::COMPANY_MANAGER]);
        $managedTeam->update(['manager_id' => $manager->id]);
        $managedEmployee = User::factory()->create([
            'company_id' => $company->id,
            'team_id' => $managedTeam->id,
            'role' => Role::EMPLOYEE,
            'email' => 'managed@example.com',
        ]);
        $otherEmployee = User::factory()->create([
            'company_id' => $company->id,
            'team_id' => $otherTeam->id,
            'role' => Role::EMPLOYEE,
            'email' => 'other@example.com',
        ]);

        $response = $this->actingAs($manager, 'sanctum')->getJson('/api/company/users');

        $response->assertStatus(200);
        $emails = collect($response->json('data'))->pluck('email')->all();
        $this->assertContains($managedEmployee->email, $emails);
        $this->assertNotContains($otherEmployee->email, $emails);
    }

    public function test_company_manager_can_only_see_and_revoke_own_employee_invites(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id, 'role' => Role::COMPANY_MANAGER]);
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => Role::COMPANY_ADMIN]);
        $ownInvite = InviteToken::create([
            'company_id' => $company->id,
            'invited_by_user_id' => $manager->id,
            'role' => Role::EMPLOYEE,
            'email' => 'own@example.com',
            'token_hash' => hash('sha256', 'own-token'),
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);
        $adminInvite = InviteToken::create([
            'company_id' => $company->id,
            'invited_by_user_id' => $admin->id,
            'role' => Role::EMPLOYEE,
            'email' => 'admin@example.com',
            'token_hash' => hash('sha256', 'admin-token'),
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($manager, 'sanctum')->getJson('/api/company/invitations');

        $response->assertStatus(200);
        $emails = collect($response->json('data'))->pluck('email')->all();
        $this->assertContains($ownInvite->email, $emails);
        $this->assertNotContains($adminInvite->email, $emails);

        $this->actingAs($manager, 'sanctum')
            ->deleteJson("/api/company/invitations/{$adminInvite->id}")
            ->assertStatus(403);
    }

    public function test_forged_role_or_active_portal_does_not_bypass_backend_role_middleware(): void
    {
        $company = Company::factory()->create();
        $employee = User::factory()->create(['company_id' => $company->id, 'role' => Role::EMPLOYEE]);

        $this->actingAs($employee, 'sanctum')->postJson('/api/company/invitations', [
            'email' => 'admin@example.com',
            'role' => Role::COMPANY_ADMIN->value,
            'activePortal' => 'company',
        ])->assertStatus(403);

        $this->actingAs($employee, 'sanctum')->getJson('/api/company/dashboard?activePortal=company&role=COMPANY_ADMIN')
            ->assertStatus(403);
    }

    public function test_me_returns_identity_but_frontend_mutation_is_not_authoritative(): void
    {
        $company = Company::factory()->create();
        $employee = User::factory()->create(['company_id' => $company->id, 'role' => Role::EMPLOYEE]);

        $this->actingAs($employee, 'sanctum')->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('id', $employee->id)
            ->assertJsonPath('companyId', $company->id)
            ->assertJsonPath('roles.0', Role::EMPLOYEE->value);

        $this->actingAs($employee, 'sanctum')->getJson('/api/admin/companies?companyId='.$company->id.'&role=ELYO_ADMIN')
            ->assertStatus(403);
    }

    public function test_company_admin_forged_company_id_is_ignored_when_listing_users(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => Role::COMPANY_ADMIN]);
        $ownUser = User::factory()->create(['company_id' => $company->id, 'role' => Role::EMPLOYEE, 'email' => 'own@example.com']);
        $foreignUser = User::factory()->create(['company_id' => $otherCompany->id, 'role' => Role::EMPLOYEE, 'email' => 'foreign@example.com']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/company/users?company_id='.$otherCompany->id.'&companyId='.$otherCompany->id);

        $response->assertStatus(200);
        $emails = collect($response->json('data'))->pluck('email')->all();
        $this->assertContains($ownUser->email, $emails);
        $this->assertNotContains($foreignUser->email, $emails);
    }
}
