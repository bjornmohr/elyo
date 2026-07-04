<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Measure;
use App\Models\MeasureCheckinToken;
use App\Models\MeasureParticipation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeasureExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execution_returns_derived_status_matrix(): void
    {
        $company = Company::factory()->create(['anonymity_threshold' => 3]);
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);

        $cases = [
            ['attributes' => ['status' => 'COMPLETED'], 'expected' => 'COMPLETED'],
            ['attributes' => ['status' => 'ACTIVE', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDay()], 'expected' => 'COMPLETED'],
            ['attributes' => ['status' => 'SUGGESTED'], 'expected' => 'PLANNED'],
            ['attributes' => ['status' => 'ACTIVE', 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(3)], 'expected' => 'UPCOMING'],
            ['attributes' => ['status' => 'ACTIVE', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()], 'expected' => 'RUNNING'],
            ['attributes' => ['status' => 'ACTIVE'], 'expected' => 'RUNNING'],
        ];

        foreach ($cases as $case) {
            $measure = Measure::factory()->create([
                'company_id' => $company->id,
                'team_id' => null,
                'created_by' => $admin->id,
                ...$case['attributes'],
            ]);

            $this->actingAs($admin)
                ->getJson("/api/company/measures/{$measure->id}/execution")
                ->assertOk()
                ->assertJsonPath('data.measureId', $measure->id)
                ->assertJsonPath('data.derivedStatus', $case['expected']);
        }
    }

    public function test_measure_list_exposes_backend_derived_status(): void
    {
        $company = Company::factory()->create(['anonymity_threshold' => 3]);
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);
        $measure = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => null,
            'created_by' => $admin->id,
            'status' => 'ACTIVE',
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(3),
        ]);

        $this->actingAs($admin)
            ->getJson('/api/company/measures')
            ->assertOk()
            ->assertJsonPath('data.0.id', $measure->id)
            ->assertJsonPath('data.0.derivedStatus', 'UPCOMING');
    }

    public function test_execution_exposes_checkin_state_for_active_token(): void
    {
        $company = Company::factory()->create(['anonymity_threshold' => 3]);
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);
        $measure = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => null,
            'created_by' => $admin->id,
            'status' => 'ACTIVE',
            'verification_requirement' => 'QR_CODE',
        ]);
        $token = MeasureCheckinToken::create([
            'measure_id' => $measure->id,
            'company_id' => $company->id,
            'token_hash' => hash('sha256', 'test-token'),
            'created_by_user_id' => $admin->id,
            'valid_from' => now()->subHour(),
            'valid_until' => now()->addDay(),
        ]);

        $this->actingAs($admin)
            ->getJson("/api/company/measures/{$measure->id}/execution")
            ->assertOk()
            ->assertJsonPath('data.checkin.required', true)
            ->assertJsonPath('data.checkin.active', true)
            ->assertJsonPath('data.checkin.createdAt', $token->created_at->toIso8601String());
    }

    public function test_execution_reports_inactive_checkin_for_revoked_token_and_self_report(): void
    {
        $company = Company::factory()->create(['anonymity_threshold' => 3]);
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);
        $measure = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => null,
            'created_by' => $admin->id,
            'status' => 'ACTIVE',
            'verification_requirement' => 'SELF_REPORT',
        ]);
        MeasureCheckinToken::create([
            'measure_id' => $measure->id,
            'company_id' => $company->id,
            'token_hash' => hash('sha256', 'revoked-token'),
            'created_by_user_id' => $admin->id,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
            'revoked_at' => now()->subHour(),
        ]);

        $this->actingAs($admin)
            ->getJson("/api/company/measures/{$measure->id}/execution")
            ->assertOk()
            ->assertJsonPath('data.checkin.required', false)
            ->assertJsonPath('data.checkin.active', false)
            ->assertJsonPath('data.checkin.createdAt', null);
    }

    public function test_execution_suppresses_registered_count_below_threshold(): void
    {
        $company = Company::factory()->create(['anonymity_threshold' => 3]);
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);
        $measure = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => null,
            'created_by' => $admin->id,
            'status' => 'ACTIVE',
        ]);
        $employees = User::factory()->count(5)->create([
            'company_id' => $company->id,
            'role' => Role::EMPLOYEE,
        ]);
        $employees->take(2)->each(fn (User $employee) => MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $employee->id,
            'company_id' => $company->id,
            'team_id' => null,
        ]));

        $this->actingAs($admin)
            ->getJson("/api/company/measures/{$measure->id}/execution")
            ->assertOk()
            ->assertJsonPath('data.registeredCount', null)
            ->assertJsonPath('data.isAboveThreshold', false);
    }

    public function test_execution_returns_registered_count_above_threshold(): void
    {
        $company = Company::factory()->create(['anonymity_threshold' => 3]);
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);
        $measure = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => null,
            'created_by' => $admin->id,
            'status' => 'ACTIVE',
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
        ]));

        $this->actingAs($admin)
            ->getJson("/api/company/measures/{$measure->id}/execution")
            ->assertOk()
            ->assertJsonPath('data.registeredCount', 4)
            ->assertJsonPath('data.isAboveThreshold', true);
    }

    public function test_execution_is_not_accessible_for_foreign_company_measure(): void
    {
        $company = Company::factory()->create();
        $admin = $this->companyUser($company, Role::COMPANY_ADMIN);
        $foreignMeasure = Measure::factory()->create([
            'company_id' => Company::factory()->create()->id,
        ]);

        $this->actingAs($admin)
            ->getJson("/api/company/measures/{$foreignMeasure->id}/execution")
            ->assertNotFound();
    }

    private function companyUser(Company $company, Role $role): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
        ]);
    }
}
