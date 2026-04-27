<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use App\Enums\Role;
use App\Enums\PartnerVerificationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_registration_and_login()
    {
        $response = $this->postJson('/api/partner/register', [
            'email' => 'partner@example.com',
            'password' => 'password123',
            'name' => 'Test Partner',
            'type' => 'Gym',
            'categories' => ['fitness'],
            'description' => 'A great gym',
            'address' => '123 Main St',
            'city' => 'Berlin',
            'minimum_level' => 1,
            'nachweis_url' => 'https://example.com/doc.pdf',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['partnerId', 'token']);

        $loginResponse = $this->postJson('/api/partner/login', [
            'email' => 'partner@example.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonStructure(['partnerId', 'token', 'status']);
    }

    public function test_admin_can_review_partner()
    {
        $admin = User::factory()->create(['role' => Role::ELYO_ADMIN]);
        $partner = Partner::create([
            'id' => 'p1',
            'email' => 'p1@example.com',
            'password_hash' => 'hash',
            'name' => 'P1',
            'type' => 'Yoga',
            'categories' => ['wellness'],
            'description' => 'Desc',
            'address' => 'Addr',
            'city' => 'City',
            'minimum_level' => 1,
            'nachweis_url' => 'http://doc',
            'verification_status' => PartnerVerificationStatus::PENDING_REVIEW,
        ]);

        $response = $this->actingAs($admin)
            ->patchJson("/api/admin/partners/{$partner->id}", [
                'action' => 'approve'
            ]);

        $response->assertStatus(200);
        $this->assertEquals(PartnerVerificationStatus::VERIFIED, $partner->fresh()->verification_status);
    }
}
