<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Measure;
use App\Models\MeasureParticipation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeasureStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_prod_mode_aggregates_measure_counts_per_risk_field(): void
    {
        config(['elyo.data_mode' => 'prod']);

        $company = Company::factory()->create(['anonymity_threshold' => 3]);
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);

        foreach (['flexibility' => 1, 'workshop' => 1, 'nutrition' => 2] as $category => $count) {
            Measure::factory()->count($count)->create([
                'company_id' => $company->id,
                'team_id' => null,
                'created_by' => $admin->id,
                'category' => $category,
                'status' => 'ACTIVE',
            ]);
        }
        Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => null,
            'created_by' => $admin->id,
            'category' => 'sport',
            'status' => 'DISMISSED',
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/company/measures/statistics')
            ->assertOk()
            ->assertJsonCount(6, 'data');

        $byField = collect($response->json('data'))->keyBy('field');

        $this->assertSame(1, $byField['BACK']['measureCount']);
        $this->assertSame(1, $byField['KNOWLEDGE']['measureCount']); // workshop -> KNOWLEDGE
        $this->assertSame(2, $byField['NUTRITION']['measureCount']);
        $this->assertSame(0, $byField['MOVEMENT']['measureCount']); // dismissed excluded
        $this->assertSame(0, $byField['SLEEP']['measureCount']); // gap row
        $this->assertNull($byField['SLEEP']['avgParticipationRate']);
        $this->assertTrue($byField['SLEEP']['isAboveThreshold']);
        // Impact and trend have no prod data source yet.
        $this->assertNull($byField['BACK']['avgImpactRating']);
        $this->assertNull($byField['BACK']['fieldTrend30d']);
    }

    public function test_prod_mode_averages_participation_and_suppresses_below_threshold(): void
    {
        config(['elyo.data_mode' => 'prod']);

        $company = Company::factory()->create(['anonymity_threshold' => 3]);
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);
        $employees = User::factory()->count(5)->create([
            'company_id' => $company->id,
            'role' => Role::EMPLOYEE,
        ]);

        $backMeasure = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => null,
            'created_by' => $admin->id,
            'category' => 'flexibility',
            'status' => 'ACTIVE',
        ]);
        $employees->take(4)->each(fn (User $employee) => MeasureParticipation::factory()->create([
            'measure_id' => $backMeasure->id,
            'user_id' => $employee->id,
            'company_id' => $company->id,
            'team_id' => null,
        ]));

        $mentalMeasure = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => null,
            'created_by' => $admin->id,
            'category' => 'mental',
            'status' => 'ACTIVE',
        ]);
        $employees->take(2)->each(fn (User $employee) => MeasureParticipation::factory()->create([
            'measure_id' => $mentalMeasure->id,
            'user_id' => $employee->id,
            'company_id' => $company->id,
            'team_id' => null,
        ]));

        $response = $this->actingAs($admin)
            ->getJson('/api/company/measures/statistics')
            ->assertOk();

        $byField = collect($response->json('data'))->keyBy('field');

        $this->assertSame(80, (int) $byField['BACK']['avgParticipationRate']);
        $this->assertTrue($byField['BACK']['isAboveThreshold']);
        // Only measure in the field is below threshold -> suppressed field.
        $this->assertNull($byField['STRESS_MENTAL']['avgParticipationRate']);
        $this->assertFalse($byField['STRESS_MENTAL']['isAboveThreshold']);
    }

    public function test_demo_mode_returns_exact_base_values_for_reference_company(): void
    {
        config(['elyo.data_mode' => 'demo']);

        $company = Company::factory()->create(['slug' => 'demo-gmbh']);
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);

        $response = $this->actingAs($admin)
            ->getJson('/api/company/measures/statistics')
            ->assertOk()
            ->assertJsonCount(6, 'data');

        $byField = collect($response->json('data'))->keyBy('field');

        $this->assertSame(1, $byField['BACK']['measureCount']);
        $this->assertSame(78, (int) $byField['BACK']['avgParticipationRate']);
        $this->assertSame(15, $byField['BACK']['fieldTrend30d']);
        $this->assertSame(0, $byField['SLEEP']['measureCount']);
        $this->assertFalse($byField['STRESS_MENTAL']['isAboveThreshold']);
        $this->assertNull($byField['STRESS_MENTAL']['avgParticipationRate']);
        $this->assertSame(4, $byField['MOVEMENT']['avgImpactRating']);
        $this->assertTrue($byField['MOVEMENT']['impactIsPreliminary']);
    }

    public function test_demo_mode_varies_deterministically_per_company(): void
    {
        config(['elyo.data_mode' => 'demo']);

        $reference = Company::factory()->create(['slug' => 'demo-gmbh']);
        $other = Company::factory()->create(['slug' => 'other-gmbh']);
        $referenceAdmin = $this->companyUser($reference, Role::COMPANY_ADMIN);
        $otherAdmin = $this->companyUser($other, Role::COMPANY_ADMIN);

        $referenceData = $this->actingAs($referenceAdmin)->getJson('/api/company/measures/statistics')->json('data');
        $first = $this->actingAs($otherAdmin)->getJson('/api/company/measures/statistics')->json('data');
        $second = $this->actingAs($otherAdmin)->getJson('/api/company/measures/statistics')->json('data');

        $this->assertSame($first, $second, 'demo variance must be stable across requests');
        $this->assertNotSame($referenceData, $first, 'non-reference company must get varied values');

        // Invariants survive the jitter.
        foreach ($first as $row) {
            if ($row['measureCount'] === 0) {
                $this->assertNull($row['avgParticipationRate']);
                $this->assertNull($row['avgImpactRating']);
            }
            if ($row['avgParticipationRate'] !== null) {
                $this->assertGreaterThanOrEqual(0, $row['avgParticipationRate']);
                $this->assertLessThanOrEqual(100, $row['avgParticipationRate']);
            }
            if ($row['avgImpactRating'] !== null) {
                $this->assertGreaterThanOrEqual(1, $row['avgImpactRating']);
                $this->assertLessThanOrEqual(5, $row['avgImpactRating']);
            }
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
