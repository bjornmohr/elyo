<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\InviteToken;
use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Company::factory()->create(['id' => 'company-1', 'name' => 'ELYO']);
    }

    public function test_user_can_login_with_correct_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@elyo.de',
            'password_hash' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@elyo.de',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['access_token', 'token_type']);
    }

    public function test_user_cannot_login_with_incorrect_password()
    {
        $user = User::factory()->create([
            'email' => 'test@elyo.de',
            'password_hash' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@elyo.de',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_cannot_login_if_inactive()
    {
        $user = User::factory()->create([
            'email' => 'test@elyo.de',
            'password_hash' => Hash::make('password123'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@elyo.de',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_get_me_info()
    {
        $user = User::factory()->create([
            'email' => 'test@elyo.de',
            'role' => Role::EMPLOYEE,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'email' => 'test@elyo.de',
                'role' => 'EMPLOYEE',
            ]);
    }

    public function test_role_restriction_works_for_admin_endpoint()
    {
        $employee = User::factory()->create(['role' => Role::EMPLOYEE]);
        $admin = User::factory()->create(['role' => Role::ELYO_ADMIN]);

        // Employee should be forbidden
        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/admin/stats')
            ->assertStatus(403);

        // Admin should be allowed
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/stats')
            ->assertStatus(200);
    }

    public function test_invite_verification_works()
    {
        $invite = InviteToken::create([
            'id' => 'token-1',
            'token' => 'valid-token',
            'email' => 'invited@test.com',
            'role' => Role::EMPLOYEE,
            'company_id' => 'company-1',
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->getJson('/api/auth/invite/verify/valid-token');

        $response->assertStatus(200)
            ->assertJson([
                'email' => 'invited@test.com',
                'role' => 'EMPLOYEE',
            ]);
    }

    public function test_invite_acceptance_works()
    {
        $invite = InviteToken::create([
            'id' => 'token-1',
            'token' => 'valid-token',
            'email' => 'invited@test.com',
            'role' => Role::EMPLOYEE,
            'company_id' => 'company-1',
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/auth/invite/accept', [
            'token' => 'valid-token',
            'name' => 'New User',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['access_token']);

        $this->assertDatabaseHas('users', [
            'email' => 'invited@test.com',
            'name' => 'New User',
        ]);

        $this->assertNotNull($invite->refresh()->used_at);
    }
}
