<?php

namespace Tests\Privacy;

use App\Enums\Role;
use Tests\Support\HealthLeakAssertions;
use Tests\Support\PrivacySeeder;
use Tests\TestCase;

class CompanyWellbeingPrivacyTest extends TestCase
{
    use HealthLeakAssertions;

    public function test_company_wellbeing_blocks_are_reporting_pending_without_numbers(): void
    {
        $fixtures = new PrivacySeeder;
        $fixtures->run();
        $companyAdmin = $fixtures->users[Role::COMPANY_ADMIN->value];

        $dashboard = $this->actingAs($companyAdmin, 'sanctum')
            ->getJson('/api/company/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('company.status', 'reporting_pending')
            ->assertJsonPath('company.data', null)
            ->assertJsonPath('teams.0.metrics.status', 'reporting_pending')
            ->assertJsonPath('teams.0.metrics.data', null)
            ->assertJsonPath('trend.status', 'reporting_pending')
            ->assertJsonPath('trend.data', null)
            ->assertJsonPath('company.responseCount', null)
            ->assertJsonPath('company.isAboveThreshold', null);

        $this->assertResponseHasNoHealthLeaks(
            $dashboard,
            '/api/company/dashboard',
            $fixtures->healthSubjectIds(),
        );

        $reports = $this->actingAs($companyAdmin, 'sanctum')
            ->getJson('/api/company/reports')
            ->assertStatus(200)
            ->assertExactJson(['status' => 'reporting_pending', 'data' => null]);

        $this->assertResponseHasNoHealthLeaks(
            $reports,
            '/api/company/reports',
            $fixtures->healthSubjectIds(),
        );
    }
}
