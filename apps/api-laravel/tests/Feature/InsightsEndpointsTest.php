<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Measure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsightsEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_mode_serves_concept_module_payloads(): void
    {
        config(['elyo.data_mode' => 'demo']);

        $company = Company::factory()->create(['slug' => 'demo-gmbh']);
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);

        $landscape = $this->actingAs($admin)->getJson('/api/company/risk-landscape')->assertOk();
        $this->assertCount(6, $landscape->json('data.fields'));
        $this->assertSame('SLEEP', $landscape->json('data.fields.0.field'));
        $this->assertSame(82, $landscape->json('data.fields.0.score'));
        $this->assertCount(6, $landscape->json('data.fields.0.monthlyScores'));
        $this->assertSame('STRESS_MENTAL', $landscape->json('data.fields.2.field'));
        $this->assertNotEmpty($landscape->json('data.recommendations'));

        $funnel = $this->actingAs($admin)->getJson('/api/company/usage-funnel')->assertOk();
        $this->assertSame('2026-06', $funnel->json('data.cohort'));
        $this->assertCount(5, $funnel->json('data.stages'));
        $this->assertSame(210, $funnel->json('data.stages.0.count'));

        $radar = $this->actingAs($admin)->getJson('/api/company/infection-radar')->assertOk();
        $this->assertSame('ELEVATED', $radar->json('data.overallStatus'));
        $this->assertCount(7, $radar->json('data.symptomReports7d'));
        $this->assertSame(142, $radar->json('data.rkiIncidence.value'));

        $dashboard = $this->actingAs($admin)->getJson('/api/company/dashboard')->assertOk();
        $this->assertNotNull($dashboard->json('executiveSummary'));
        $this->assertSame(71, $dashboard->json('executiveSummary.kpis.healthIndex.value'));
    }

    public function test_prod_mode_returns_empty_concept_payloads(): void
    {
        config(['elyo.data_mode' => 'prod']);

        $company = Company::factory()->create();
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);

        $this->actingAs($admin)->getJson('/api/company/risk-landscape')
            ->assertOk()
            ->assertExactJson(['data' => []]);
        $this->actingAs($admin)->getJson('/api/company/usage-funnel')
            ->assertOk()
            ->assertExactJson(['data' => null]);
        $this->actingAs($admin)->getJson('/api/company/infection-radar')
            ->assertOk()
            ->assertExactJson(['data' => null]);

        $dashboard = $this->actingAs($admin)->getJson('/api/company/dashboard')->assertOk();
        $this->assertNull($dashboard->json('executiveSummary'));
    }

    public function test_impact_returns_data_only_for_completed_measures_in_demo_mode(): void
    {
        config(['elyo.data_mode' => 'demo']);

        $company = Company::factory()->create(['slug' => 'demo-gmbh']);
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);
        $completed = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => null,
            'created_by' => $admin->id,
            'category' => 'nutrition',
            'status' => 'COMPLETED',
        ]);
        $active = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => null,
            'created_by' => $admin->id,
            'category' => 'nutrition',
            'status' => 'ACTIVE',
        ]);

        $impact = $this->actingAs($admin)
            ->getJson("/api/company/measures/{$completed->id}/impact")
            ->assertOk();
        $this->assertSame($completed->id, $impact->json('data.measureId'));
        $this->assertSame(9, $impact->json('data.netEffect'));
        $this->assertSame(45, $impact->json('data.participants.n'));
        $this->assertSame(
            $impact->json('data.participants.scoreAfter') - $impact->json('data.participants.scoreBefore')
            - ($impact->json('data.control.scoreAfter') - $impact->json('data.control.scoreBefore')),
            $impact->json('data.netEffect'),
        );

        $this->actingAs($admin)
            ->getJson("/api/company/measures/{$active->id}/impact")
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_impact_is_null_in_prod_mode_and_scoped_to_company(): void
    {
        config(['elyo.data_mode' => 'prod']);

        $company = Company::factory()->create();
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);
        $completed = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => null,
            'created_by' => $admin->id,
            'status' => 'COMPLETED',
        ]);
        $foreign = Measure::factory()->create([
            'company_id' => Company::factory()->create()->id,
            'status' => 'COMPLETED',
        ]);

        $this->actingAs($admin)
            ->getJson("/api/company/measures/{$completed->id}/impact")
            ->assertOk()
            ->assertExactJson(['data' => null]);

        $this->actingAs($admin)
            ->getJson("/api/company/measures/{$foreign->id}/impact")
            ->assertNotFound();
    }

    public function test_auth_payload_carries_feature_flags_per_mode(): void
    {
        $company = Company::factory()->create();
        $user = $this->companyUser($company, Role::COMPANY_ADMIN);

        config(['elyo.data_mode' => 'demo']);
        $this->actingAs($user)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('measureImpactEnabled', true)
            ->assertJsonPath('riskLandscapeEnabled', true)
            ->assertJsonPath('usageFunnelEnabled', true)
            ->assertJsonPath('infectionRadarEnabled', true);

        config(['elyo.data_mode' => 'prod']);
        $this->actingAs($user)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('measureImpactEnabled', false)
            ->assertJsonPath('riskLandscapeEnabled', false)
            ->assertJsonPath('usageFunnelEnabled', false)
            ->assertJsonPath('infectionRadarEnabled', false);
    }

    public function test_demo_variance_is_deterministic_and_keeps_funnel_monotone(): void
    {
        config(['elyo.data_mode' => 'demo']);

        $company = Company::factory()->create(['slug' => 'variance-check-gmbh']);
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);

        $first = $this->actingAs($admin)->getJson('/api/company/usage-funnel')->json('data');
        $second = $this->actingAs($admin)->getJson('/api/company/usage-funnel')->json('data');

        $this->assertSame($first, $second, 'funnel variance must be stable across requests');

        $counts = array_column($first['stages'], 'count');
        $sorted = $counts;
        rsort($sorted);
        $this->assertSame($sorted, $counts, 'funnel stages must stay monotonically decreasing');
        $this->assertSame(100, (int) $first['stages'][0]['rate']);

        $this->assertCount(4, $first['categories']);
        foreach ($first['categories'] as $category) {
            $this->assertGreaterThanOrEqual($category['measureStarted'], $category['recommendationReceived']);
            $this->assertGreaterThanOrEqual($category['active14d'], $category['measureStarted']);
        }

        $radar = $this->actingAs($admin)->getJson('/api/company/infection-radar')->json('data');
        $this->assertContains($radar['overallStatus'], ['NORMAL', 'ELEVATED', 'CRITICAL']);
        foreach ($radar['symptomReports7d'] as $point) {
            $this->assertGreaterThanOrEqual(0, $point['count']);
        }
    }

    private function companyUser(Company $company, Role $role): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
        ]);
    }
}
