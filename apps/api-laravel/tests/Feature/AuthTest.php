<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\InviteToken;
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
        $company = Company::factory()->create();
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

    // --- Me ---

    public function test_authenticated_user_can_get_me_info()
    {
        $user = $this->createUserWithRole(Role::EMPLOYEE, Company::factory()->create()->id);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'email', 'name', 'roles', 'allowedPortals']);
    }

    // --- Admin company management ---

    public function test_elyo_admin_can_create_company()
    {
        $admin = $this->createPlatformAdmin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/companies', [
            'name' => 'Test Company',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('companies', ['name' => 'Test Company']);
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
