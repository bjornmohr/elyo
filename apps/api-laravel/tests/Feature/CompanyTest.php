<?php

namespace Tests\Feature;

use App\Enums\QuestionType;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Measure;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\Team;
use App\Models\User;
use App\Models\WellbeingEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    protected $company;

    protected $admin;

    protected $manager;

    protected $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['anonymity_threshold' => 3]);
        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => Role::COMPANY_ADMIN,
        ]);
        $this->team = Team::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Tech Team',
        ]);
        $this->manager = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => Role::COMPANY_MANAGER,
        ]);
        $this->team->update(['manager_id' => $this->manager->id]);
    }

    public function test_company_dashboard_aggregation_and_threshold()
    {
        // 1. Below threshold
        $employees = User::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'role' => Role::EMPLOYEE,
        ]);

        foreach ($employees as $emp) {
            WellbeingEntry::factory()->create([
                'user_id' => $emp->id,
                'company_id' => $this->company->id,
                'mood' => 8,
                'stress' => 2,
                'energy' => 9,
                'score' => 8.5,
                'period_key' => '2024-W10',
            ]);
        }

        $response = $this->actingAs($this->admin)->getJson('/api/company/dashboard');

        $response->assertStatus(200);
        $response->assertJsonPath('company.isAboveThreshold', false);
        $response->assertJsonPath('company.responseCount', 2);
        $response->assertJsonPath('company.avgScore', 0);

        // 2. Above threshold
        $emp3 = User::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'role' => Role::EMPLOYEE,
        ]);
        WellbeingEntry::factory()->create([
            'user_id' => $emp3->id,
            'company_id' => $this->company->id,
            'mood' => 5,
            'stress' => 5,
            'energy' => 5,
            'score' => 5.0,
            'period_key' => '2024-W10',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/company/dashboard');
        $response->assertJsonPath('company.isAboveThreshold', true);
        $response->assertJsonPath('company.responseCount', 3);
        // (8.5 + 8.5 + 5.0) / 3 = 22 / 3 = 7.333 -> 7.3
        $response->assertJsonPath('company.avgScore', 7.3);
    }

    public function test_company_dashboard_participation_counts_distinct_active_employees()
    {
        $employees = User::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'role' => Role::EMPLOYEE,
        ]);

        foreach (range(1, 10) as $index) {
            WellbeingEntry::factory()->create([
                'user_id' => $employees->first()->id,
                'company_id' => $this->company->id,
                'period_key' => sprintf('2024-W%02d', $index),
            ]);
        }

        $response = $this->actingAs($this->admin)->getJson('/api/company/dashboard');

        $response->assertStatus(200);
        $response->assertJsonPath('company.responseCount', 10);
        $response->assertJsonPath('company.respondentCount', 1);
        $response->assertJsonPath('company.eligibleEmployeeCount', 3);
        $response->assertJsonPath('company.participationRate', 33);
        $response->assertJsonPath('company.isAboveThreshold', false);
    }

    public function test_company_dashboard_participation_is_capped_at_one_hundred_percent()
    {
        $employees = User::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'role' => Role::EMPLOYEE,
        ]);

        foreach ($employees as $employee) {
            foreach (range(1, 4) as $index) {
                WellbeingEntry::factory()->create([
                    'user_id' => $employee->id,
                    'company_id' => $this->company->id,
                    'period_key' => sprintf('2024-W%02d', $index),
                ]);
            }
        }

        $response = $this->actingAs($this->admin)->getJson('/api/company/dashboard');

        $response->assertStatus(200);
        $response->assertJsonPath('company.responseCount', 12);
        $response->assertJsonPath('company.respondentCount', 3);
        $response->assertJsonPath('company.eligibleEmployeeCount', 3);
        $response->assertJsonPath('company.participationRate', 100);
        $response->assertJsonPath('company.isAboveThreshold', true);
    }

    public function test_manager_scoping_to_team()
    {
        $otherTeam = Team::factory()->create(['company_id' => $this->company->id, 'name' => 'Sales']);

        // Manager's team entries
        $emps1 = User::factory()->count(3)->create(['company_id' => $this->company->id, 'team_id' => $this->team->id]);
        foreach ($emps1 as $emp) {
            WellbeingEntry::factory()->create(['user_id' => $emp->id, 'company_id' => $this->company->id, 'score' => 8.0]);
        }

        // Other team entries
        $emps2 = User::factory()->count(3)->create(['company_id' => $this->company->id, 'team_id' => $otherTeam->id]);
        foreach ($emps2 as $emp) {
            WellbeingEntry::factory()->create(['user_id' => $emp->id, 'company_id' => $this->company->id, 'score' => 2.0]);
        }

        // Manager dashboard should only see their team's average (8.0)
        $response = $this->actingAs($this->manager)->getJson('/api/company/dashboard');
        $response->assertStatus(200);
        $this->assertEquals(8.0, $response->json('company.avgScore'));
        $response->assertJsonCount(1, 'teams');
        $response->assertJsonPath('teams.0.name', 'Tech Team');

        // Admin dashboard should see overall average ( (8*3 + 2*3) / 6 = 30 / 6 = 5.0 )
        $response = $this->actingAs($this->admin)->getJson('/api/company/dashboard');
        $this->assertEquals(5.0, $response->json('company.avgScore'));
        $response->assertJsonCount(2, 'teams');
    }

    public function test_survey_results_anonymity_threshold()
    {
        $survey = Survey::factory()->create(['company_id' => $this->company->id]);
        $question = SurveyQuestion::factory()->create([
            'survey_id' => $survey->id,
            'type' => QuestionType::SCALE,
        ]);

        // 2 responses (below threshold of 3)
        for ($i = 0; $i < 2; $i++) {
            $resp = SurveyResponse::factory()->create(['survey_id' => $survey->id, 'company_id' => $this->company->id]);
            SurveyAnswer::factory()->create([
                'response_id' => $resp->id,
                'question_id' => $question->id,
                'scale_value' => 10,
            ]);
        }

        $response = $this->actingAs($this->admin)->getJson("/api/company/surveys/{$survey->id}/results");
        $response->assertStatus(403);
        $response->assertJsonPath('isAboveThreshold', false);

        // 3rd response
        $resp3 = SurveyResponse::factory()->create(['survey_id' => $survey->id, 'company_id' => $this->company->id]);
        SurveyAnswer::factory()->create([
            'response_id' => $resp3->id,
            'question_id' => $question->id,
            'scale_value' => 4,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/company/surveys/{$survey->id}/results");
        $response->assertStatus(200);
        $response->assertJsonPath('data.isAboveThreshold', true);
        $this->assertEquals(8.0, $response->json('data.questions.0.avgValue')); // (10+10+4)/3 = 8
    }

    public function test_measure_creation_and_transitions()
    {
        $data = [
            'title' => 'New Yoga Class',
            'category' => 'sport',
            'description' => 'A weekly yoga class for everyone.',
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/company/measures', $data);
        $response->assertStatus(201);
        $measureId = $response->json('data.id');

        // Valid transition: ACTIVE -> COMPLETED
        $response = $this->actingAs($this->admin)->patchJson("/api/company/measures/{$measureId}", [
            'status' => 'COMPLETED',
        ]);
        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'COMPLETED');

        // Invalid transition: COMPLETED -> ACTIVE (not in VALID_TRANSITIONS)
        $response = $this->actingAs($this->admin)->patchJson("/api/company/measures/{$measureId}", [
            'status' => 'ACTIVE',
        ]);
        $response->assertStatus(400);
    }

    public function test_manager_can_see_global_and_managed_team_measures()
    {
        $otherTeam = Team::factory()->create(['company_id' => $this->company->id, 'name' => 'Sales']);

        Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'title' => 'Global measure',
            'created_by' => $this->admin->id,
        ]);
        Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'title' => 'Managed team measure',
            'created_by' => $this->admin->id,
        ]);
        Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => $otherTeam->id,
            'title' => 'Other team measure',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->manager)->getJson('/api/company/measures');

        $response->assertStatus(200);
        $this->assertEqualsCanonicalizing(
            ['Global measure', 'Managed team measure'],
            collect($response->json('data'))->pluck('title')->all()
        );
    }

    public function test_company_can_create_team_with_manager_and_survey_with_dates()
    {
        $response = $this->actingAs($this->admin)->postJson('/api/company/teams', [
            'name' => 'People Team',
            'description' => 'Responsible for employee experience.',
            'color' => '#0d9488',
            'managerId' => $this->manager->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'People Team');
        $response->assertJsonPath('data.managerId', $this->manager->id);

        $teamId = $response->json('data.id');

        $response = $this->actingAs($this->admin)->postJson('/api/company/surveys', [
            'title' => 'Quarterly pulse',
            'description' => 'Quarterly employee survey.',
            'startsAt' => '2026-06-01T09:00:00',
            'endsAt' => '2026-06-30T17:00:00',
            'isAnonymous' => true,
            'teamIds' => [$teamId],
            'questions' => [
                [
                    'text' => 'How balanced is your workload?',
                    'type' => 'SCALE',
                    'order' => 0,
                    'isRequired' => true,
                    'scaleMinLabel' => 'Too high',
                    'scaleMaxLabel' => 'Balanced',
                ],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'Quarterly pulse');
        $response->assertJsonPath('data.questionsCount', 1);
    }
}
