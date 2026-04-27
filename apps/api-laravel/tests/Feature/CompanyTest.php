<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Models\Team;
use App\Models\WellbeingEntry;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveyAnswer;
use App\Models\Measure;
use App\Enums\Role;
use App\Services\AnonymityService;
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
            'type' => \App\Enums\QuestionType::SCALE,
        ]);

        // 2 responses (below threshold of 3)
        for ($i = 0; $i < 2; $i++) {
            $resp = SurveyResponse::factory()->create(['survey_id' => $survey->id, 'company_id' => $this->company->id]);
            SurveyAnswer::factory()->create([
                'response_id' => $resp->id,
                'question_id' => $question->id,
                'scale_value' => 10
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
            'scale_value' => 4
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
            'status' => 'COMPLETED'
        ]);
        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'COMPLETED');

        // Invalid transition: COMPLETED -> ACTIVE (not in VALID_TRANSITIONS)
        $response = $this->actingAs($this->admin)->patchJson("/api/company/measures/{$measureId}", [
            'status' => 'ACTIVE'
        ]);
        $response->assertStatus(400);
    }
}
