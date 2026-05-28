<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\InviteToken;
use App\Models\Team;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRole(Role $role, ?int $companyId = null): User
    {
        $user = User::factory()->create([
            'company_id' => $companyId,
        ]);
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function createCompanyWithAdmin(): array
    {
        $company = Company::factory()->create(['team_layer_enabled' => true]);
        $admin = $this->createUserWithRole(Role::COMPANY_ADMIN, $company->id);

        return [$company, $admin];
    }

    private function createPlatformAdmin(): User
    {
        $user = User::factory()->platformAdmin()->create();
        UserRole::create(['user_id' => $user->id, 'role' => Role::ELYO_ADMIN]);

        return $user;
    }

    // --- Login ---

    public function test_user_can_login_with_correct_credentials()
    {
        $user = $this->createUserWithRole(Role::EMPLOYEE, Company::factory()->create()->id);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['access_token', 'token_type', 'user', 'activePortal', 'allowedPortals']);
    }

    public function test_user_cannot_login_with_incorrect_password()
    {
        $user = $this->createUserWithRole(Role::EMPLOYEE, Company::factory()->create()->id);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_does_not_reveal_email_existence()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(422);
    }

    public function test_inactive_user_cannot_login()
    {
        $user = User::factory()->create(['status' => 'inactive', 'company_id' => Company::factory()->create()->id]);
        UserRole::create(['user_id' => $user->id, 'role' => Role::EMPLOYEE]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(422);
    }

    // --- Portal validation ---

    public function test_login_to_company_portal_fails_for_employee_only_user()
    {
        $user = $this->createUserWithRole(Role::EMPLOYEE, Company::factory()->create()->id);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'requested_portal' => 'company',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'PORTAL_FORBIDDEN');
    }

    public function test_login_to_employee_portal_fails_for_company_only_user()
    {
        $user = $this->createUserWithRole(Role::COMPANY_ADMIN, Company::factory()->create()->id);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'requested_portal' => 'employee',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'PORTAL_FORBIDDEN');
    }

    public function test_manager_only_user_without_team_layer_does_not_get_company_portal(): void
    {
        $company = Company::factory()->create(['team_layer_enabled' => false]);
        $user = $this->createUserWithRole(Role::COMPANY_MANAGER, $company->id);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('activePortal', null)
            ->assertJsonPath('allowedPortals', []);

        $meResponse = $this->actingAs($user, 'sanctum')->getJson('/api/auth/me');

        $meResponse->assertOk()
            ->assertJsonPath('allowedPortals', []);
    }

    public function test_manager_employee_user_without_team_layer_falls_back_to_employee_portal(): void
    {
        $company = Company::factory()->create(['team_layer_enabled' => false]);
        $user = $this->createUserWithRole(Role::COMPANY_MANAGER, $company->id);
        UserRole::create(['user_id' => $user->id, 'role' => Role::EMPLOYEE]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('activePortal', 'employee')
            ->assertJsonPath('allowedPortals', ['employee']);
    }

    public function test_manager_only_user_with_team_layer_gets_company_portal(): void
    {
        $company = Company::factory()->create(['team_layer_enabled' => true]);
        $user = $this->createUserWithRole(Role::COMPANY_MANAGER, $company->id);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('activePortal', 'company')
            ->assertJsonPath('allowedPortals', ['company']);
    }

    public function test_company_admin_without_team_layer_keeps_company_portal(): void
    {
        $company = Company::factory()->create(['team_layer_enabled' => false]);
        $user = $this->createUserWithRole(Role::COMPANY_ADMIN, $company->id);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('activePortal', 'company')
            ->assertJsonPath('allowedPortals', ['company']);
    }

    public function test_manager_only_user_without_team_layer_cannot_request_company_portal(): void
    {
        $company = Company::factory()->create(['team_layer_enabled' => false]);
        $user = $this->createUserWithRole(Role::COMPANY_MANAGER, $company->id);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'requested_portal' => 'company',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'PORTAL_FORBIDDEN');
    }

    // --- Me ---

    public function test_authenticated_user_can_get_me_info()
    {
        $user = $this->createUserWithRole(Role::EMPLOYEE, Company::factory()->create()->id);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'email', 'name', 'roles', 'allowedPortals']);
    }

    public function test_company_defaults_to_team_layer_disabled(): void
    {
        $company = Company::factory()->create();

        $this->assertFalse($company->team_layer_enabled);
        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'team_layer_enabled' => false,
        ]);
    }

    public function test_auth_responses_include_team_layer_enabled(): void
    {
        $company = Company::factory()->create(['team_layer_enabled' => true]);
        $user = $this->createUserWithRole(Role::COMPANY_ADMIN, $company->id);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonPath('user.teamLayerEnabled', true);

        $meResponse = $this->actingAs($user, 'sanctum')->getJson('/api/auth/me');

        $meResponse->assertStatus(200)
            ->assertJsonPath('teamLayerEnabled', true);
    }

    // --- Admin company management ---

    public function test_elyo_admin_can_create_company()
    {
        $admin = $this->createPlatformAdmin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/companies', [
            'name' => 'Test Company',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('companies', [
            'name' => 'Test Company',
            'team_layer_enabled' => false,
        ]);
    }

    public function test_elyo_admin_can_create_company_with_team_layer_enabled(): void
    {
        $admin = $this->createPlatformAdmin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/companies', [
            'name' => 'Team Layer Company',
            'team_layer_enabled' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.team_layer_enabled', true);
        $this->assertDatabaseHas('companies', [
            'name' => 'Team Layer Company',
            'team_layer_enabled' => true,
        ]);
    }

    public function test_elyo_admin_can_update_company_team_layer_setting(): void
    {
        $admin = $this->createPlatformAdmin();
        $company = Company::factory()->create(['team_layer_enabled' => false]);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/admin/companies/{$company->id}", [
            'team_layer_enabled' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.team_layer_enabled', true);
        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'team_layer_enabled' => true,
        ]);
    }

    public function test_non_admin_cannot_create_company()
    {
        $user = $this->createUserWithRole(Role::COMPANY_ADMIN, Company::factory()->create()->id);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/admin/companies', [
            'name' => 'Test Company',
        ]);

        $response->assertStatus(403);
    }

    public function test_employee_cannot_create_company()
    {
        $user = $this->createUserWithRole(Role::EMPLOYEE, Company::factory()->create()->id);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/admin/companies', [
            'name' => 'Test Company',
        ]);

        $response->assertStatus(403);
    }

    public function test_elyo_admin_can_invite_first_company_admin()
    {
        $admin = $this->createPlatformAdmin();
        $company = Company::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/companies/{$company->id}/invite-company-admin", [
                'email' => 'newadmin@test.com',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invite_tokens', ['email' => 'newadmin@test.com']);
    }

    public function test_elyo_admin_can_manage_points_config()
    {
        $admin = $this->createPlatformAdmin();

        $getResponse = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/points-config');
        $getResponse->assertStatus(200)
            ->assertJsonPath('data.daily_checkin', 10);

        $updateResponse = $this->actingAs($admin, 'sanctum')->putJson('/api/admin/points-config', [
            'daily_checkin' => 15,
            'streak_7days' => 60,
            'streak_30days' => 250,
            'anamnesis_completed' => 120,
            'medical_document_upload' => 30,
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.daily_checkin', 15)
            ->assertJsonPath('data.streak_7days', 60);
    }

    // --- Company invitation management ---

    public function test_company_admin_can_invite_employee()
    {
        [$company, $admin] = $this->createCompanyWithAdmin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/company/invitations', [
            'email' => 'employee@test.com',
            'role' => 'EMPLOYEE',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invite_tokens', ['email' => 'employee@test.com', 'role' => 'EMPLOYEE']);
    }

    public function test_company_admin_can_invite_employee_without_team_when_team_layer_disabled(): void
    {
        $company = Company::factory()->create(['team_layer_enabled' => false]);
        $admin = $this->createUserWithRole(Role::COMPANY_ADMIN, $company->id);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/company/invitations', [
            'email' => 'employee-no-team@test.com',
            'role' => Role::EMPLOYEE->value,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.teamId', null);
        $this->assertDatabaseHas('invite_tokens', [
            'email' => 'employee-no-team@test.com',
            'role' => Role::EMPLOYEE->value,
            'team_id' => null,
        ]);
    }

    public function test_company_admin_cannot_send_team_id_when_team_layer_disabled(): void
    {
        $company = Company::factory()->create(['team_layer_enabled' => false]);
        $admin = $this->createUserWithRole(Role::COMPANY_ADMIN, $company->id);
        $team = Team::factory()->create(['company_id' => $company->id, 'manager_id' => null]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/company/invitations', [
            'email' => 'disabled-team-id@test.com',
            'role' => Role::EMPLOYEE->value,
            'teamId' => $team->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'TEAM_LAYER_DISABLED');
        $this->assertDatabaseMissing('invite_tokens', ['email' => 'disabled-team-id@test.com']);
    }

    public function test_company_admin_cannot_invite_manager_when_team_layer_disabled(): void
    {
        $company = Company::factory()->create(['team_layer_enabled' => false]);
        $admin = $this->createUserWithRole(Role::COMPANY_ADMIN, $company->id);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/company/invitations', [
            'email' => 'manager-disabled-by-admin@test.com',
            'role' => Role::COMPANY_MANAGER->value,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'TEAM_LAYER_DISABLED');
        $this->assertDatabaseMissing('invite_tokens', ['email' => 'manager-disabled-by-admin@test.com']);
    }

    public function test_company_admin_can_create_employee_invite_with_valid_same_company_team(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();
        $team = Team::factory()->create(['company_id' => $company->id, 'manager_id' => null]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/company/invitations', [
            'email' => 'teamed-employee@test.com',
            'role' => Role::EMPLOYEE->value,
            'teamId' => $team->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.teamId', $team->id);

        $this->assertDatabaseHas('invite_tokens', [
            'email' => 'teamed-employee@test.com',
            'role' => Role::EMPLOYEE->value,
            'team_id' => $team->id,
        ]);
    }

    public function test_company_owner_invite_creation_is_rejected_by_validation(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();
        $team = Team::factory()->create(['company_id' => $company->id, 'manager_id' => null]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/company/invitations', [
            'email' => 'owner-invite@test.com',
            'role' => Role::COMPANY_OWNER->value,
            'teamId' => $team->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('role');
        $this->assertDatabaseMissing('invite_tokens', [
            'email' => 'owner-invite@test.com',
        ]);
    }

    public function test_company_invitations_list_includes_team_id_for_team_scoped_invites(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();
        $team = Team::factory()->create(['company_id' => $company->id, 'manager_id' => null]);

        $createResponse = $this->actingAs($admin, 'sanctum')->postJson('/api/company/invitations', [
            'email' => 'listed-team-invite@test.com',
            'role' => Role::EMPLOYEE->value,
            'teamId' => $team->id,
        ]);

        $createResponse->assertStatus(201);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/company/invitations');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'email' => 'listed-team-invite@test.com',
                'teamId' => $team->id,
            ]);
    }

    public function test_invite_creation_rejects_foreign_company_team(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();
        $foreignCompany = Company::factory()->create();
        $foreignTeam = Team::factory()->create(['company_id' => $foreignCompany->id, 'manager_id' => null]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/company/invitations', [
            'email' => 'foreign-team@test.com',
            'role' => Role::EMPLOYEE->value,
            'teamId' => $foreignTeam->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('invite_tokens', ['email' => 'foreign-team@test.com']);
    }

    public function test_manager_can_invite_employee_only_into_managed_team(): void
    {
        [$company] = $this->createCompanyWithAdmin();
        $manager = $this->createUserWithRole(Role::COMPANY_MANAGER, $company->id);
        $managedTeam = Team::factory()->create(['company_id' => $company->id, 'manager_id' => $manager->id]);

        $response = $this->actingAs($manager, 'sanctum')->postJson('/api/company/invitations', [
            'email' => 'managed-invite@test.com',
            'role' => Role::EMPLOYEE->value,
            'teamId' => $managedTeam->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invite_tokens', [
            'email' => 'managed-invite@test.com',
            'role' => Role::EMPLOYEE->value,
            'team_id' => $managedTeam->id,
        ]);
    }

    public function test_manager_cannot_invite_when_team_layer_disabled(): void
    {
        $company = Company::factory()->create(['team_layer_enabled' => false]);
        $manager = $this->createUserWithRole(Role::COMPANY_MANAGER, $company->id);
        $managedTeam = Team::factory()->create(['company_id' => $company->id, 'manager_id' => $manager->id]);

        $response = $this->actingAs($manager, 'sanctum')->postJson('/api/company/invitations', [
            'email' => 'manager-disabled@test.com',
            'role' => Role::EMPLOYEE->value,
            'teamId' => $managedTeam->id,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'PORTAL_FORBIDDEN');
        $this->assertDatabaseMissing('invite_tokens', ['email' => 'manager-disabled@test.com']);
    }

    public function test_manager_cannot_invite_into_unmanaged_team(): void
    {
        [$company] = $this->createCompanyWithAdmin();
        $manager = $this->createUserWithRole(Role::COMPANY_MANAGER, $company->id);
        $unmanagedTeam = Team::factory()->create(['company_id' => $company->id, 'manager_id' => null]);

        $response = $this->actingAs($manager, 'sanctum')->postJson('/api/company/invitations', [
            'email' => 'unmanaged-invite@test.com',
            'role' => Role::EMPLOYEE->value,
            'teamId' => $unmanagedTeam->id,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
        $this->assertDatabaseMissing('invite_tokens', ['email' => 'unmanaged-invite@test.com']);
    }

    public function test_company_admin_can_create_and_accept_manager_invite_with_team_affiliation(): void
    {
        [$company, $admin] = $this->createCompanyWithAdmin();
        $team = Team::factory()->create(['company_id' => $company->id, 'manager_id' => null]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/company/invitations', [
            'email' => 'manager-with-team@test.com',
            'role' => Role::COMPANY_MANAGER->value,
            'teamId' => $team->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.teamId', $team->id);
        $this->assertDatabaseHas('invite_tokens', [
            'email' => 'manager-with-team@test.com',
            'role' => Role::COMPANY_MANAGER->value,
            'team_id' => $team->id,
        ]);

        $acceptResponse = $this->postJson('/api/auth/invite/accept', [
            'token' => $response->json('data.invite_token'),
            'name' => 'Manager With Team',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $acceptResponse->assertStatus(200)
            ->assertJsonPath('user.teamId', $team->id);
        $this->assertDatabaseHas('users', [
            'email' => 'manager-with-team@test.com',
            'company_id' => $company->id,
            'team_id' => $team->id,
        ]);
        $manager = User::where('email', 'manager-with-team@test.com')->firstOrFail();
        $this->assertTrue($manager->hasRole(Role::COMPANY_MANAGER));
    }

    public function test_manager_cannot_invite_non_employee_roles(): void
    {
        [$company] = $this->createCompanyWithAdmin();
        $manager = $this->createUserWithRole(Role::COMPANY_MANAGER, $company->id);
        $managedTeam = Team::factory()->create(['company_id' => $company->id, 'manager_id' => $manager->id]);

        $response = $this->actingAs($manager, 'sanctum')->postJson('/api/company/invitations', [
            'email' => 'manager-invites-manager@test.com',
            'role' => Role::COMPANY_MANAGER->value,
            'teamId' => $managedTeam->id,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
        $this->assertDatabaseMissing('invite_tokens', ['email' => 'manager-invites-manager@test.com']);
    }

    public function test_company_admin_cannot_invite_into_another_company()
    {
        [$company1, $admin1] = $this->createCompanyWithAdmin();
        $company2 = Company::factory()->create();
        $existingUser = $this->createUserWithRole(Role::EMPLOYEE, $company2->id);

        $response = $this->actingAs($admin1, 'sanctum')->postJson('/api/company/invitations', [
            'email' => $existingUser->email,
            'role' => 'EMPLOYEE',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'COMPANY_CONFLICT');
    }

    public function test_employee_cannot_invite_users()
    {
        $company = Company::factory()->create();
        $employee = $this->createUserWithRole(Role::EMPLOYEE, $company->id);

        $response = $this->actingAs($employee, 'sanctum')->postJson('/api/company/invitations', [
            'email' => 'someone@test.com',
            'role' => 'EMPLOYEE',
        ]);

        $response->assertStatus(403);
    }

    public function test_employee_cannot_access_company_dashboard()
    {
        $company = Company::factory()->create();
        $employee = $this->createUserWithRole(Role::EMPLOYEE, $company->id);

        $response = $this->actingAs($employee, 'sanctum')->getJson('/api/company/dashboard');

        $response->assertStatus(403);
    }

    public function test_company_admin_cannot_access_another_companys_data()
    {
        [$company1, $admin1] = $this->createCompanyWithAdmin();
        [$company2, $admin2] = $this->createCompanyWithAdmin();

        // admin1 should only see their own company's users
        $response = $this->actingAs($admin1, 'sanctum')->getJson('/api/company/users');
        $response->assertStatus(200);

        // The response should not contain admin2's data
        $users = $response->json('data');
        foreach ($users as $user) {
            $this->assertNotEquals($admin2->email, $user['email']);
        }
    }

    // --- Invite accept ---

    public function test_invite_accept_creates_user_with_correct_role()
    {
        $company = Company::factory()->create();
        $rawToken = 'test-token-123';

        InviteToken::create([
            'company_id' => $company->id,
            'email' => 'invited@test.com',
            'role' => Role::EMPLOYEE,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/auth/invite/accept', [
            'token' => $rawToken,
            'name' => 'New User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['access_token']);
        $this->assertDatabaseHas('users', ['email' => 'invited@test.com', 'company_id' => $company->id]);
        $this->assertDatabaseHas('user_roles', ['role' => 'EMPLOYEE']);
    }

    public function test_invite_acceptance_creates_new_user_with_invite_team_id(): void
    {
        $company = Company::factory()->create();
        $team = Team::factory()->create(['company_id' => $company->id, 'manager_id' => null]);
        $rawToken = 'team-token-123';

        InviteToken::create([
            'company_id' => $company->id,
            'team_id' => $team->id,
            'email' => 'teamed-invited@test.com',
            'role' => Role::EMPLOYEE,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/auth/invite/accept', [
            'token' => $rawToken,
            'name' => 'Teamed User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.teamId', $team->id);
        $this->assertDatabaseHas('users', [
            'email' => 'teamed-invited@test.com',
            'company_id' => $company->id,
            'team_id' => $team->id,
        ]);
    }

    public function test_forged_request_team_id_during_invite_accept_is_ignored(): void
    {
        $company = Company::factory()->create();
        $inviteTeam = Team::factory()->create(['company_id' => $company->id, 'manager_id' => null]);
        $forgedTeam = Team::factory()->create(['company_id' => $company->id, 'manager_id' => null]);
        $rawToken = 'team-token-forged';

        InviteToken::create([
            'company_id' => $company->id,
            'team_id' => $inviteTeam->id,
            'email' => 'forged-team@test.com',
            'role' => Role::EMPLOYEE,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/auth/invite/accept', [
            'token' => $rawToken,
            'name' => 'Forged Team',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'team_id' => $forgedTeam->id,
            'teamId' => $forgedTeam->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.teamId', $inviteTeam->id);
        $this->assertDatabaseHas('users', [
            'email' => 'forged-team@test.com',
            'team_id' => $inviteTeam->id,
        ]);
    }

    public function test_invite_acceptance_rejects_invite_with_foreign_team_id_without_mutating_users(): void
    {
        $company = Company::factory()->create();
        $foreignCompany = Company::factory()->create();
        $foreignTeam = Team::factory()->create(['company_id' => $foreignCompany->id, 'manager_id' => null]);
        $rawToken = 'foreign-team-accept-token';

        InviteToken::create([
            'company_id' => $company->id,
            'team_id' => $foreignTeam->id,
            'email' => 'foreign-team-accept@test.com',
            'role' => Role::EMPLOYEE,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/auth/invite/accept', [
            'token' => $rawToken,
            'name' => 'Foreign Team',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_INVITE_TEAM');
        $this->assertDatabaseMissing('users', [
            'email' => 'foreign-team-accept@test.com',
        ]);
        $this->assertDatabaseHas('invite_tokens', [
            'email' => 'foreign-team-accept@test.com',
            'status' => 'pending',
        ]);
    }

    public function test_invite_acceptance_rejects_existing_user_invite_with_foreign_team_id_without_updating_user(): void
    {
        $company = Company::factory()->create();
        $foreignCompany = Company::factory()->create();
        $foreignTeam = Team::factory()->create(['company_id' => $foreignCompany->id, 'manager_id' => null]);
        $existingUser = $this->createUserWithRole(Role::EMPLOYEE, $company->id);
        $existingUser->update(['email' => 'existing-foreign-team@test.com', 'team_id' => null]);
        $rawToken = 'existing-foreign-team-token';

        InviteToken::create([
            'company_id' => $company->id,
            'team_id' => $foreignTeam->id,
            'email' => $existingUser->email,
            'role' => Role::EMPLOYEE,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/auth/invite/accept', [
            'token' => $rawToken,
            'name' => 'Existing Foreign Team',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_INVITE_TEAM');
        $this->assertNull($existingUser->refresh()->team_id);
        $this->assertDatabaseHas('invite_tokens', [
            'email' => $existingUser->email,
            'status' => 'pending',
        ]);
    }

    public function test_invite_accept_cannot_override_role()
    {
        $company = Company::factory()->create();
        $rawToken = 'test-token-456';

        InviteToken::create([
            'company_id' => $company->id,
            'email' => 'invited2@test.com',
            'role' => Role::EMPLOYEE,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/auth/invite/accept', [
            'token' => $rawToken,
            'name' => 'New User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'ELYO_ADMIN', // attempt to escalate
        ]);

        $response->assertStatus(200);
        // User should have EMPLOYEE role, not ELYO_ADMIN
        $user = User::where('email', 'invited2@test.com')->first();
        $this->assertTrue($user->hasRole(Role::EMPLOYEE));
        $this->assertFalse($user->hasRole(Role::ELYO_ADMIN));
    }

    public function test_invite_accept_cannot_override_company()
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $rawToken = 'test-token-789';

        InviteToken::create([
            'company_id' => $company->id,
            'email' => 'invited3@test.com',
            'role' => Role::EMPLOYEE,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/auth/invite/accept', [
            'token' => $rawToken,
            'name' => 'New User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_id' => $otherCompany->id, // attempt to override
        ]);

        $response->assertStatus(200);
        $user = User::where('email', 'invited3@test.com')->first();
        $this->assertEquals($company->id, $user->company_id);
    }

    public function test_invite_for_email_already_in_another_company_is_rejected()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $existingUser = $this->createUserWithRole(Role::EMPLOYEE, $company1->id);

        $rawToken = 'test-token-conflict';
        InviteToken::create([
            'company_id' => $company2->id,
            'email' => $existingUser->email,
            'role' => Role::EMPLOYEE,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/auth/invite/accept', [
            'token' => $rawToken,
            'name' => 'Existing User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'COMPANY_CONFLICT');
    }

    public function test_existing_user_with_different_team_accepting_team_invite_is_rejected(): void
    {
        $company = Company::factory()->create();
        $existingTeam = Team::factory()->create(['company_id' => $company->id, 'manager_id' => null]);
        $inviteTeam = Team::factory()->create(['company_id' => $company->id, 'manager_id' => null]);
        $existingUser = $this->createUserWithRole(Role::EMPLOYEE, $company->id);
        $existingUser->update(['email' => 'existing-team@test.com', 'team_id' => $existingTeam->id]);
        $rawToken = 'team-conflict-token';

        InviteToken::create([
            'company_id' => $company->id,
            'team_id' => $inviteTeam->id,
            'email' => $existingUser->email,
            'role' => Role::EMPLOYEE,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/auth/invite/accept', [
            'token' => $rawToken,
            'name' => 'Existing User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'TEAM_CONFLICT');

        $this->assertSame($existingTeam->id, $existingUser->refresh()->team_id);
        $this->assertDatabaseHas('invite_tokens', [
            'email' => $existingUser->email,
            'status' => 'pending',
        ]);
    }

    public function test_expired_invite_cannot_be_accepted()
    {
        $company = Company::factory()->create();
        $rawToken = 'expired-token';

        InviteToken::create([
            'company_id' => $company->id,
            'email' => 'expired@test.com',
            'role' => Role::EMPLOYEE,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/auth/invite/accept', [
            'token' => $rawToken,
            'name' => 'Late User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_used_invite_cannot_be_accepted_twice()
    {
        $company = Company::factory()->create();
        $rawToken = 'used-token';

        InviteToken::create([
            'company_id' => $company->id,
            'email' => 'used@test.com',
            'role' => Role::EMPLOYEE,
            'token_hash' => hash('sha256', $rawToken),
            'status' => 'accepted',
            'accepted_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/auth/invite/accept', [
            'token' => $rawToken,
            'name' => 'Duplicate User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
    }
}
