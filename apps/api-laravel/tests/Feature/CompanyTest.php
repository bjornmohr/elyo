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
        $employees = User::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'role' => Role::EMPLOYEE,
        ]);

        // 2 responses (below threshold of 3)
        for ($i = 0; $i < 2; $i++) {
            $resp = SurveyResponse::factory()->create([
                'survey_id' => $survey->id,
                'company_id' => $this->company->id,
                'user_id' => $employees[$i]->id,
            ]);
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
        $resp3 = SurveyResponse::factory()->create([
            'survey_id' => $survey->id,
            'company_id' => $this->company->id,
            'user_id' => $employees[2]->id,
        ]);
        SurveyAnswer::factory()->create([
            'response_id' => $resp3->id,
            'question_id' => $question->id,
            'scale_value' => 10,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/company/surveys/{$survey->id}/results");
        $response->assertStatus(200);
        $response->assertJsonPath('data.isAboveThreshold', true);
        $this->assertEquals(10.0, $response->json('data.questions.0.avgValue'));
    }

    public function test_survey_results_show_scale_distribution_when_all_buckets_meet_threshold()
    {
        $survey = Survey::factory()->create(['company_id' => $this->company->id, 'status' => 'ACTIVE']);
        $question = SurveyQuestion::factory()->create([
            'survey_id' => $survey->id,
            'type' => QuestionType::SCALE,
        ]);
        $employees = User::factory()->count(6)->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'role' => Role::EMPLOYEE,
        ]);

        foreach ($employees as $index => $employee) {
            $this->createSurveyAnswer($survey, $question, $employee, [
                'scale_value' => $index < 3 ? 4 : 10,
            ]);
        }

        $response = $this->actingAs($this->admin)->getJson("/api/company/surveys/{$survey->id}/results");

        $response->assertStatus(200);
        $response->assertJsonPath('data.questions.0.isSuppressed', false);
        $response->assertJsonPath('data.questions.0.suppressedCount', 0);
        $this->assertEquals(7.0, $response->json('data.questions.0.avgValue'));
        $response->assertJsonPath('data.questions.0.minValue', 4);
        $response->assertJsonPath('data.questions.0.maxValue', 10);
        $response->assertJsonCount(2, 'data.questions.0.distribution');
        $response->assertJsonFragment(['value' => 4, 'count' => 3, 'percentage' => 50]);
        $response->assertJsonFragment(['value' => 10, 'count' => 3, 'percentage' => 50]);
    }

    public function test_manager_only_sees_survey_results_for_their_team()
    {
        $otherTeam = Team::factory()->create(['company_id' => $this->company->id]);
        $survey = Survey::factory()->create(['company_id' => $this->company->id, 'status' => 'ACTIVE']);
        $question = SurveyQuestion::factory()->create([
            'survey_id' => $survey->id,
            'type' => QuestionType::SCALE,
        ]);

        $managedEmployees = User::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'role' => Role::EMPLOYEE,
        ]);
        $otherEmployees = User::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'team_id' => $otherTeam->id,
            'role' => Role::EMPLOYEE,
        ]);

        foreach ($managedEmployees as $employee) {
            $response = SurveyResponse::factory()->create([
                'survey_id' => $survey->id,
                'company_id' => $this->company->id,
                'user_id' => $employee->id,
            ]);
            SurveyAnswer::factory()->create([
                'response_id' => $response->id,
                'question_id' => $question->id,
                'scale_value' => 4,
            ]);
        }

        foreach ($otherEmployees as $employee) {
            $response = SurveyResponse::factory()->create([
                'survey_id' => $survey->id,
                'company_id' => $this->company->id,
                'user_id' => $employee->id,
            ]);
            SurveyAnswer::factory()->create([
                'response_id' => $response->id,
                'question_id' => $question->id,
                'scale_value' => 10,
            ]);
        }

        $managerResponse = $this->actingAs($this->manager)->getJson("/api/company/surveys/{$survey->id}/results");
        $managerResponse->assertStatus(200);
        $this->assertEquals(4.0, $managerResponse->json('data.questions.0.avgValue'));
        $managerResponse->assertJsonPath('data.participation.responseCount', 3);

        $adminResponse = $this->actingAs($this->admin)->getJson("/api/company/surveys/{$survey->id}/results");
        $adminResponse->assertStatus(200);
        $this->assertEquals(7.0, $adminResponse->json('data.questions.0.avgValue'));
        $adminResponse->assertJsonPath('data.participation.responseCount', 6);
    }

    public function test_survey_results_suppress_small_multiple_choice_buckets()
    {
        $survey = Survey::factory()->create(['company_id' => $this->company->id, 'status' => 'ACTIVE']);
        $question = SurveyQuestion::factory()->create([
            'survey_id' => $survey->id,
            'type' => QuestionType::MULTIPLE_CHOICE,
            'options' => ['Keep visible', 'Hide small'],
        ]);
        $employees = User::factory()->count(4)->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'role' => Role::EMPLOYEE,
        ]);

        foreach ($employees as $index => $employee) {
            $this->createSurveyAnswer($survey, $question, $employee, [
                'choice_value' => $index < 3 ? 'Keep visible' : 'Hide small',
            ]);
        }

        $response = $this->actingAs($this->admin)->getJson("/api/company/surveys/{$survey->id}/results");

        $response->assertStatus(200);
        $response->assertJsonPath('data.questions.0.isSuppressed', true);
        $response->assertJsonPath('data.questions.0.suppressedCount', null);
        $response->assertJsonPath('data.questions.0.suppressionReason', 'DISTRIBUTION_SUPPRESSED');
        $response->assertJsonPath('data.questions.0.options', []);
        $response->assertJsonMissing(['value' => 'Keep visible']);
        $response->assertJsonMissing(['value' => 'Hide small']);
    }

    public function test_survey_results_show_multiple_choice_distribution_when_all_buckets_meet_threshold()
    {
        $survey = Survey::factory()->create(['company_id' => $this->company->id, 'status' => 'ACTIVE']);
        $question = SurveyQuestion::factory()->create([
            'survey_id' => $survey->id,
            'type' => QuestionType::MULTIPLE_CHOICE,
            'options' => ['Option A', 'Option B'],
        ]);
        $employees = User::factory()->count(6)->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'role' => Role::EMPLOYEE,
        ]);

        foreach ($employees as $index => $employee) {
            $this->createSurveyAnswer($survey, $question, $employee, [
                'choice_value' => $index < 3 ? 'Option A' : 'Option B',
            ]);
        }

        $response = $this->actingAs($this->admin)->getJson("/api/company/surveys/{$survey->id}/results");

        $response->assertStatus(200);
        $response->assertJsonPath('data.questions.0.isSuppressed', false);
        $response->assertJsonPath('data.questions.0.suppressedCount', 0);
        $response->assertJsonCount(2, 'data.questions.0.options');
        $response->assertJsonFragment(['value' => 'Option A', 'count' => 3, 'percentage' => 50]);
        $response->assertJsonFragment(['value' => 'Option B', 'count' => 3, 'percentage' => 50]);
        $response->assertJsonMissing(['suppressionReason' => 'DISTRIBUTION_SUPPRESSED']);
    }

    public function test_survey_results_suppress_yes_no_minority_split()
    {
        $survey = Survey::factory()->create(['company_id' => $this->company->id, 'status' => 'ACTIVE']);
        $question = SurveyQuestion::factory()->create([
            'survey_id' => $survey->id,
            'type' => QuestionType::YES_NO,
        ]);
        $employees = User::factory()->count(4)->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'role' => Role::EMPLOYEE,
        ]);

        foreach ($employees as $index => $employee) {
            $this->createSurveyAnswer($survey, $question, $employee, [
                'bool_value' => $index === 0,
            ]);
        }

        $response = $this->actingAs($this->admin)->getJson("/api/company/surveys/{$survey->id}/results");

        $response->assertStatus(200);
        $response->assertJsonPath('data.questions.0.answerCount', 4);
        $response->assertJsonPath('data.questions.0.isSuppressed', true);
        $response->assertJsonPath('data.questions.0.suppressedCount', null);
        $response->assertJsonPath('data.questions.0.trueCount', null);
        $response->assertJsonPath('data.questions.0.falseCount', null);
        $response->assertJsonPath('data.questions.0.truePercentage', null);
        $response->assertJsonPath('data.questions.0.falsePercentage', null);
    }

    public function test_survey_results_suppress_small_scale_distribution_buckets()
    {
        $survey = Survey::factory()->create(['company_id' => $this->company->id, 'status' => 'ACTIVE']);
        $question = SurveyQuestion::factory()->create([
            'survey_id' => $survey->id,
            'type' => QuestionType::SCALE,
        ]);
        $employees = User::factory()->count(4)->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'role' => Role::EMPLOYEE,
        ]);

        foreach ($employees as $index => $employee) {
            $this->createSurveyAnswer($survey, $question, $employee, [
                'scale_value' => $index < 3 ? 8 : 2,
            ]);
        }

        $response = $this->actingAs($this->admin)->getJson("/api/company/surveys/{$survey->id}/results");

        $response->assertStatus(200);
        $response->assertJsonPath('data.questions.0.isSuppressed', true);
        $response->assertJsonPath('data.questions.0.suppressedCount', null);
        $response->assertJsonPath('data.questions.0.suppressionReason', 'DISTRIBUTION_SUPPRESSED');
        $response->assertJsonPath('data.questions.0.avgValue', null);
        $response->assertJsonPath('data.questions.0.minValue', null);
        $response->assertJsonPath('data.questions.0.maxValue', null);
        $response->assertJsonPath('data.questions.0.distribution', []);
        $response->assertJsonMissing(['value' => 8]);
        $response->assertJsonMissing(['value' => 2]);
    }

    public function test_survey_results_suppress_question_when_answer_count_is_below_threshold()
    {
        $survey = Survey::factory()->create(['company_id' => $this->company->id, 'status' => 'ACTIVE']);
        $question = SurveyQuestion::factory()->create([
            'survey_id' => $survey->id,
            'type' => QuestionType::TEXT,
            'is_required' => false,
        ]);
        $employees = User::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'role' => Role::EMPLOYEE,
        ]);

        foreach ($employees as $index => $employee) {
            $surveyResponse = SurveyResponse::factory()->create([
                'survey_id' => $survey->id,
                'company_id' => $this->company->id,
                'user_id' => $employee->id,
            ]);

            if ($index < 2) {
                SurveyAnswer::factory()->create([
                    'response_id' => $surveyResponse->id,
                    'question_id' => $question->id,
                    'scale_value' => null,
                    'text_value' => "Raw private answer {$index}",
                ]);
            }
        }

        $response = $this->actingAs($this->admin)->getJson("/api/company/surveys/{$survey->id}/results");

        $response->assertStatus(200);
        $response->assertJsonPath('data.isAboveThreshold', true);
        $response->assertJsonPath('data.questions.0.isSuppressed', true);
        $response->assertJsonPath('data.questions.0.answerCount', null);
        $response->assertJsonPath('data.questions.0.suppressedCount', null);
        $response->assertJsonPath('data.questions.0.suppressionReason', 'QUESTION_THRESHOLD_NOT_MET');
        $response->assertJsonMissing(['answerCount' => 2]);
        $response->assertJsonMissing(['text_value' => 'Raw private answer 0']);
        $response->assertJsonMissing(['textValue' => 'Raw private answer 0']);

        $questionResult = $response->json('data.questions.0');
        $this->assertArrayNotHasKey('distribution', $questionResult);
        $this->assertArrayNotHasKey('options', $questionResult);
        $this->assertArrayNotHasKey('trueCount', $questionResult);
        $this->assertArrayNotHasKey('falseCount', $questionResult);
    }

    public function test_draft_surveys_can_be_edited_and_activated_by_allowed_owner()
    {
        $survey = Survey::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->manager->id,
            'status' => 'DRAFT',
        ]);
        $survey->teams()->sync([$this->team->id]);
        SurveyQuestion::factory()->create([
            'survey_id' => $survey->id,
            'type' => QuestionType::SCALE,
        ]);

        $response = $this->actingAs($this->manager)->patchJson("/api/company/surveys/{$survey->id}", [
            'title' => 'Updated draft survey',
            'questions' => [
                [
                    'text' => 'Updated question?',
                    'type' => 'YES_NO',
                    'order' => 0,
                    'isRequired' => true,
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Updated draft survey');
        $response->assertJsonPath('data.questionsCount', 1);

        $response = $this->actingAs($this->manager)->postJson("/api/company/surveys/{$survey->id}/activate");
        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'ACTIVE');

        $response = $this->actingAs($this->manager)->patchJson("/api/company/surveys/{$survey->id}", [
            'title' => 'Should not update',
        ]);

        $response->assertStatus(403);
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

    private function createSurveyAnswer(Survey $survey, SurveyQuestion $question, User $employee, array $answer): SurveyAnswer
    {
        $response = SurveyResponse::factory()->create([
            'survey_id' => $survey->id,
            'company_id' => $survey->company_id,
            'user_id' => $employee->id,
        ]);

        return SurveyAnswer::factory()->create(array_merge([
            'response_id' => $response->id,
            'question_id' => $question->id,
            'scale_value' => null,
            'bool_value' => null,
            'choice_value' => null,
            'text_value' => null,
        ], $answer));
    }
}
