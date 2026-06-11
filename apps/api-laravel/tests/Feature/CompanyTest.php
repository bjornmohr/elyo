<?php

namespace Tests\Feature;

use App\Enums\QuestionType;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Measure;
use App\Models\MeasureCheckinToken;
use App\Models\MeasureParticipation;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\Team;
use App\Models\User;
use App\Models\UserRole;
use App\Models\WellbeingEntry;
use App\Services\MeasureCheckinTokenService;
use Carbon\Carbon;
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

        $this->company = Company::factory()->create([
            'anonymity_threshold' => 3,
            'team_layer_enabled' => true,
        ]);
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
        $response->assertJsonPath('company.responseCount', null);
        $response->assertJsonPath('company.respondentCount', null);
        $response->assertJsonPath('company.eligibleEmployeeCount', null);
        $response->assertJsonPath('company.participationRate', null);
        $response->assertJsonPath('company.avgScore', null);
        $response->assertJsonPath('company.suppressionReason', 'ANONYMITY_THRESHOLD_NOT_MET');
        $response->assertJsonMissingPath('teams.0.memberCount');

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
        $response->assertJsonMissingPath('trend.0.respondents');
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
        $response->assertJsonPath('company.isAboveThreshold', false);
        $response->assertJsonPath('company.responseCount', null);
        $response->assertJsonPath('company.respondentCount', null);
        $response->assertJsonPath('company.eligibleEmployeeCount', null);
        $response->assertJsonPath('company.participationRate', null);
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
        $emps1 = User::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'role' => Role::EMPLOYEE,
        ]);
        foreach ($emps1 as $emp) {
            WellbeingEntry::factory()->create(['user_id' => $emp->id, 'company_id' => $this->company->id, 'score' => 8.0]);
        }

        // Other team entries
        $emps2 = User::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'team_id' => $otherTeam->id,
            'role' => Role::EMPLOYEE,
        ]);
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
        $response->assertJsonPath('suppressionReason', 'ANONYMITY_THRESHOLD_NOT_MET');
        $response->assertJsonMissingPath('current');
        $response->assertJsonMissingPath('participation');

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
        $response->assertJsonPath('data.questions.0.answerCount', 6);
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
        $response->assertJsonPath('data.questions.0.answerCount', null);
        $response->assertJsonPath('data.questions.0.suppressedCount', null);
        $response->assertJsonPath('data.questions.0.suppressionReason', 'DISTRIBUTION_SUPPRESSED');
        $response->assertJsonPath('data.questions.0.options', []);
        $response->assertJsonMissing(['answerCount' => 4]);
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
        $response->assertJsonPath('data.questions.0.answerCount', 6);
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
        $response->assertJsonPath('data.questions.0.answerCount', null);
        $response->assertJsonPath('data.questions.0.isSuppressed', true);
        $response->assertJsonPath('data.questions.0.suppressedCount', null);
        $response->assertJsonPath('data.questions.0.suppressionReason', 'DISTRIBUTION_SUPPRESSED');
        $response->assertJsonPath('data.questions.0.trueCount', null);
        $response->assertJsonPath('data.questions.0.falseCount', null);
        $response->assertJsonPath('data.questions.0.truePercentage', null);
        $response->assertJsonPath('data.questions.0.falsePercentage', null);
        $response->assertJsonMissing(['answerCount' => 4]);
    }

    public function test_survey_results_show_yes_no_split_when_all_buckets_meet_threshold()
    {
        $survey = Survey::factory()->create(['company_id' => $this->company->id, 'status' => 'ACTIVE']);
        $question = SurveyQuestion::factory()->create([
            'survey_id' => $survey->id,
            'type' => QuestionType::YES_NO,
        ]);
        $employees = User::factory()->count(6)->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'role' => Role::EMPLOYEE,
        ]);

        foreach ($employees as $index => $employee) {
            $this->createSurveyAnswer($survey, $question, $employee, [
                'bool_value' => $index < 3,
            ]);
        }

        $response = $this->actingAs($this->admin)->getJson("/api/company/surveys/{$survey->id}/results");

        $response->assertStatus(200);
        $response->assertJsonPath('data.questions.0.answerCount', 6);
        $response->assertJsonPath('data.questions.0.isSuppressed', false);
        $response->assertJsonPath('data.questions.0.suppressedCount', 0);
        $response->assertJsonPath('data.questions.0.trueCount', 3);
        $response->assertJsonPath('data.questions.0.falseCount', 3);
        $response->assertJsonPath('data.questions.0.truePercentage', 50);
        $response->assertJsonPath('data.questions.0.falsePercentage', 50);
        $response->assertJsonMissing(['suppressionReason' => 'DISTRIBUTION_SUPPRESSED']);
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
        $response->assertJsonPath('data.questions.0.answerCount', null);
        $response->assertJsonPath('data.questions.0.suppressedCount', null);
        $response->assertJsonPath('data.questions.0.suppressionReason', 'DISTRIBUTION_SUPPRESSED');
        $response->assertJsonPath('data.questions.0.avgValue', null);
        $response->assertJsonPath('data.questions.0.minValue', null);
        $response->assertJsonPath('data.questions.0.maxValue', null);
        $response->assertJsonPath('data.questions.0.distribution', []);
        $response->assertJsonMissing(['answerCount' => 4]);
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

    public function test_reports_require_distinct_respondents_for_trend_points()
    {
        $activeEmployee = User::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'role' => Role::EMPLOYEE,
        ]);
        $inactiveEmployees = User::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'status' => 'inactive',
            'role' => Role::EMPLOYEE,
        ]);

        foreach ($inactiveEmployees->prepend($activeEmployee) as $employee) {
            WellbeingEntry::factory()->create([
                'user_id' => $employee->id,
                'company_id' => $this->company->id,
                'score' => 8.0,
                'period_key' => '2026-W20',
            ]);
        }

        $response = $this->actingAs($this->admin)->getJson('/api/company/reports');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');

        $employees = User::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'role' => Role::EMPLOYEE,
        ]);

        foreach ($employees as $employee) {
            WellbeingEntry::factory()->create([
                'user_id' => $employee->id,
                'company_id' => $this->company->id,
                'score' => 8.0,
                'period_key' => '2026-W20',
            ]);
        }

        $response = $this->actingAs($this->admin)->getJson('/api/company/reports');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonMissingPath('data.0.respondents');
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

    public function test_measure_creation_accepts_domain_fields_and_derives_visibility_scope(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/company/measures', [
            'title' => 'Onsite resilience session',
            'category' => 'mental',
            'description' => 'A guided session for the Tech Team.',
            'teamId' => $this->team->id,
            'measureOrigin' => 'ELYO_TEMPLATE',
            'deliveryType' => 'ONSITE',
            'executionType' => 'GUIDED_SESSION',
            'verificationRequirement' => Measure::VERIFICATION_REQUIREMENT_SELF_REPORT,
            'startsAt' => '2026-06-20 09:00:00',
            'endsAt' => '2026-06-20 10:00:00',
            'durationMinutes' => 60,
            'instructions' => 'Bring comfortable clothes.',
            'locationName' => 'Training Room A',
            'locationAddress' => 'Main Street 1',
            'capacity' => 12,
            'pointsOverride' => 5,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.deliveryType', 'ONSITE')
            ->assertJsonPath('data.executionType', 'GUIDED_SESSION')
            ->assertJsonPath('data.measureOrigin', 'COMPANY_CREATED')
            ->assertJsonPath('data.verificationRequirement', Measure::VERIFICATION_REQUIREMENT_SELF_REPORT)
            ->assertJsonPath('data.visibilityScope', 'TEAM')
            ->assertJsonPath('data.durationMinutes', 60)
            ->assertJsonPath('data.locationName', 'Training Room A')
            ->assertJsonPath('data.capacity', 12)
            ->assertJsonPath('data.pointsOverride', 5);

        $this->assertDatabaseHas('measures', [
            'id' => $response->json('data.id'),
            'team_id' => $this->team->id,
            'measure_origin' => 'COMPANY_CREATED',
            'delivery_type' => 'ONSITE',
            'execution_type' => 'GUIDED_SESSION',
            'verification_requirement' => Measure::VERIFICATION_REQUIREMENT_SELF_REPORT,
            'visibility_scope' => 'TEAM',
            'duration_minutes' => 60,
            'instructions' => 'Bring comfortable clothes.',
            'location_name' => 'Training Room A',
            'location_address' => 'Main Street 1',
            'capacity' => 12,
            'points_override' => 5,
        ]);
    }

    public function test_measure_creation_uses_domain_field_defaults(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/company/measures', [
            'title' => 'Default measure',
            'category' => 'sport',
            'description' => 'A company-wide default measure.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.measureOrigin', 'COMPANY_CREATED')
            ->assertJsonPath('data.deliveryType', 'ONSITE')
            ->assertJsonPath('data.executionType', 'EVENT_PARTICIPATION')
            ->assertJsonPath('data.verificationRequirement', Measure::VERIFICATION_REQUIREMENT_SELF_REPORT)
            ->assertJsonPath('data.visibilityScope', 'COMPANY');
    }

    public function test_measure_creation_requires_valid_required_fields(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/company/measures', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'category', 'description']);
    }

    public function test_measure_creation_rejects_end_at_or_before_start_at(): void
    {
        $payload = [
            'title' => 'Invalid schedule',
            'category' => 'sport',
            'description' => 'A scheduled measure with invalid times.',
            'executionType' => 'EVENT_PARTICIPATION',
            'startsAt' => '2026-06-20 10:00:00',
        ];

        $this->actingAs($this->admin)
            ->postJson('/api/company/measures', $payload + ['endsAt' => '2026-06-20 09:59:00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['endsAt']);

        $this->actingAs($this->admin)
            ->postJson('/api/company/measures', $payload + ['endsAt' => '2026-06-20 10:00:00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['endsAt']);
    }

    public function test_measure_creation_derives_duration_for_existing_scheduled_execution_types(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/company/measures', [
            'title' => 'Derived duration',
            'category' => 'sport',
            'description' => 'A scheduled measure with derived duration.',
            'executionType' => 'GUIDED_SESSION',
            'startsAt' => '2026-06-20 09:00:00',
            'endsAt' => '2026-06-20 10:30:00',
            'durationMinutes' => 15,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.durationMinutes', 90);

        $this->assertDatabaseHas('measures', [
            'id' => $response->json('data.id'),
            'execution_type' => 'GUIDED_SESSION',
            'duration_minutes' => 90,
        ]);
    }

    public function test_measure_creation_keeps_manual_duration_for_non_scheduled_execution_types(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/company/measures', [
            'title' => 'Challenge duration',
            'category' => 'sport',
            'description' => 'A challenge can use a window without session duration.',
            'executionType' => 'CHALLENGE',
            'startsAt' => '2026-06-20 09:00:00',
            'endsAt' => '2026-06-21 09:00:00',
            'durationMinutes' => 30,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.durationMinutes', 30);
    }

    public function test_company_admin_can_rotate_measure_checkin_token_without_storing_plaintext(): void
    {
        $this->travelTo(Carbon::parse('2026-06-10 09:00:00'));
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'created_by' => $this->admin->id,
            'verification_requirement' => 'QR_CODE',
        ]);

        $first = $this->actingAs($this->admin)
            ->postJson("/api/company/measures/{$measure->id}/checkin-token")
            ->assertStatus(201)
            ->assertJsonPath('data.measureId', $measure->id)
            ->assertJsonPath('data.validFrom', '2026-06-10T09:00:00+00:00')
            ->assertJsonPath('data.revokedAt', null);

        $firstToken = $first->json('data.token');
        $this->assertIsString($firstToken);
        $this->assertStringContainsString("/employee/measure-checkins/{$firstToken}", $first->json('data.checkinPath'));
        $this->assertArrayNotHasKey('checkinUrl', $first->json('data'));
        $this->assertArrayNotHasKey('tokenHash', $first->json('data'));
        $this->assertDatabaseMissing('measure_checkin_tokens', ['token_hash' => $firstToken]);
        $this->assertDatabaseHas('measure_checkin_tokens', [
            'measure_id' => $measure->id,
            'company_id' => $this->company->id,
            'token_hash' => MeasureCheckinTokenService::hashToken($firstToken),
            'created_by_user_id' => $this->admin->id,
            'revoked_at' => null,
        ]);

        $second = $this->actingAs($this->admin)
            ->postJson("/api/company/measures/{$measure->id}/checkin-token")
            ->assertStatus(201);

        $secondToken = $second->json('data.token');
        $this->assertNotSame($firstToken, $secondToken);
        $this->assertNotNull(MeasureCheckinToken::where('token_hash', MeasureCheckinTokenService::hashToken($firstToken))->value('revoked_at'));
        $this->assertNull(MeasureCheckinToken::where('token_hash', MeasureCheckinTokenService::hashToken($secondToken))->value('revoked_at'));

        // Verify uniqueness guarantee
        $this->assertSame(1, MeasureCheckinToken::where('measure_id', $measure->id)->whereNull('revoked_at')->count());
    }

    public function test_active_token_uniqueness_guaranteed_by_database_constraint(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('SQLite does not support partial unique indexes with WHERE NULL in the same way as PostgreSQL.');
        }

        $measure = Measure::factory()->create(['verification_requirement' => 'QR_CODE']);

        MeasureCheckinToken::create([
            'measure_id' => $measure->id,
            'company_id' => $measure->company_id,
            'token_hash' => 'hash1',
            'revoked_at' => null,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        MeasureCheckinToken::create([
            'measure_id' => $measure->id,
            'company_id' => $measure->company_id,
            'token_hash' => 'hash2',
            'revoked_at' => null,
        ]);
    }

    public function test_company_cannot_rotate_checkin_token_for_self_report_measure_without_side_effects(): void
    {
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'created_by' => $this->admin->id,
            'verification_requirement' => 'SELF_REPORT',
        ]);
        $existingRawToken = bin2hex(random_bytes(32));
        $existingToken = MeasureCheckinToken::create([
            'measure_id' => $measure->id,
            'company_id' => $this->company->id,
            'token_hash' => MeasureCheckinTokenService::hashToken($existingRawToken),
            'created_by_user_id' => $this->admin->id,
            'valid_from' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/company/measures/{$measure->id}/checkin-token")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'MEASURE_DOES_NOT_ALLOW_QR_CHECKIN');

        $this->assertNull($response->json('data'));
        $this->assertSame(1, MeasureCheckinToken::where('measure_id', $measure->id)->count());
        $this->assertNull($existingToken->fresh()->revoked_at);
    }

    public function test_company_cannot_rotate_checkin_token_for_inactive_or_foreign_measure(): void
    {
        $inactive = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'COMPLETED',
            'created_by' => $this->admin->id,
            'verification_requirement' => 'QR_CODE',
        ]);
        $foreignMeasure = Measure::factory()->create([
            'company_id' => Company::factory()->create()->id,
            'team_id' => null,
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/company/measures/{$inactive->id}/checkin-token")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'MEASURE_NOT_ACTIVE');

        $this->actingAs($this->admin)
            ->postJson("/api/company/measures/{$foreignMeasure->id}/checkin-token")
            ->assertStatus(404);
    }

    public function test_manager_can_only_rotate_checkin_token_for_managed_team_measure(): void
    {
        $managedMeasure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'status' => 'ACTIVE',
            'created_by' => $this->admin->id,
            'verification_requirement' => 'QR_CODE',
        ]);
        $globalMeasure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'created_by' => $this->admin->id,
            'verification_requirement' => 'QR_CODE',
        ]);

        $this->actingAs($this->manager)
            ->postJson("/api/company/measures/{$managedMeasure->id}/checkin-token")
            ->assertStatus(201);

        $this->actingAs($this->manager)
            ->postJson("/api/company/measures/{$globalMeasure->id}/checkin-token")
            ->assertStatus(403);
    }

    public function test_measure_update_accepts_domain_fields_without_bypassing_status_transitions(): void
    {
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->patchJson("/api/company/measures/{$measure->id}", [
            'title' => 'Updated challenge',
            'category' => 'mental',
            'description' => 'Updated description for the challenge.',
            'deliveryType' => 'HYBRID',
            'executionType' => 'CHALLENGE',
            'verificationRequirement' => Measure::VERIFICATION_REQUIREMENT_SELF_REPORT,
            'startsAt' => '2026-07-01 08:00:00',
            'endsAt' => '2026-07-01 09:00:00',
            'durationMinutes' => 45,
            'instructions' => null,
            'locationName' => 'Courtyard',
            'locationAddress' => 'Office Campus',
            'capacity' => 25,
            'pointsOverride' => 0,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.title', 'Updated challenge')
            ->assertJsonPath('data.category', 'mental')
            ->assertJsonPath('data.description', 'Updated description for the challenge.')
            ->assertJsonPath('data.deliveryType', 'HYBRID')
            ->assertJsonPath('data.executionType', 'CHALLENGE')
            ->assertJsonPath('data.pointsOverride', 0);

        $this->assertDatabaseHas('measures', [
            'id' => $measure->id,
            'status' => 'ACTIVE',
            'title' => 'Updated challenge',
            'category' => 'mental',
            'description' => 'Updated description for the challenge.',
            'delivery_type' => 'HYBRID',
            'execution_type' => 'CHALLENGE',
            'verification_requirement' => Measure::VERIFICATION_REQUIREMENT_SELF_REPORT,
            'duration_minutes' => 45,
            'location_name' => 'Courtyard',
            'location_address' => 'Office Campus',
            'capacity' => 25,
            'points_override' => 0,
            'visibility_scope' => 'COMPANY',
        ]);

        $this->actingAs($this->admin)->patchJson("/api/company/measures/{$measure->id}", [
            'status' => 'ACTIVE',
        ])->assertStatus(400);
    }

    public function test_measure_update_derives_duration_for_scheduled_execution_types(): void
    {
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'execution_type' => 'EVENT_PARTICIPATION',
            'starts_at' => '2026-06-20 09:00:00',
            'ends_at' => '2026-06-20 10:00:00',
            'duration_minutes' => 60,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->patchJson("/api/company/measures/{$measure->id}", ['endsAt' => '2026-06-20 10:45:00'])
            ->assertStatus(200)
            ->assertJsonPath('data.durationMinutes', 105);

        $this->assertDatabaseHas('measures', [
            'id' => $measure->id,
            'duration_minutes' => 105,
        ]);
    }

    public function test_title_only_update_does_not_rewrite_duration_for_scheduled_execution_types(): void
    {
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'execution_type' => 'EVENT_PARTICIPATION',
            'starts_at' => '2026-06-20 09:00:00',
            'ends_at' => '2026-06-20 10:00:00',
            'duration_minutes' => 999,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->patchJson("/api/company/measures/{$measure->id}", ['title' => 'Schedule untouched'])
            ->assertStatus(200)
            ->assertJsonPath('data.durationMinutes', 999);

        $this->assertDatabaseHas('measures', [
            'id' => $measure->id,
            'duration_minutes' => 999,
        ]);
    }

    public function test_scheduled_measure_keeps_manual_duration_when_schedule_window_is_incomplete(): void
    {
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'execution_type' => 'EVENT_PARTICIPATION',
            'starts_at' => null,
            'ends_at' => null,
            'duration_minutes' => 60,
            'created_by' => $this->admin->id,
        ]);

        // The edit form resubmits the unchanged manual duration alongside
        // unrelated changes; this must not clear the stored value.
        $this->actingAs($this->admin)
            ->patchJson("/api/company/measures/{$measure->id}", [
                'title' => 'Updated without schedule',
                'durationMinutes' => 60,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.durationMinutes', 60);

        $this->assertDatabaseHas('measures', [
            'id' => $measure->id,
            'duration_minutes' => 60,
        ]);

        $this->actingAs($this->admin)
            ->patchJson("/api/company/measures/{$measure->id}", ['durationMinutes' => 45])
            ->assertStatus(200)
            ->assertJsonPath('data.durationMinutes', 45);

        $this->assertDatabaseHas('measures', [
            'id' => $measure->id,
            'duration_minutes' => 45,
        ]);
    }

    public function test_measure_update_clears_derived_duration_when_schedule_boundary_is_cleared(): void
    {
        foreach (['startsAt', 'endsAt'] as $clearedField) {
            $measure = Measure::factory()->create([
                'company_id' => $this->company->id,
                'team_id' => null,
                'status' => 'ACTIVE',
                'execution_type' => 'EVENT_PARTICIPATION',
                'starts_at' => '2026-06-20 09:00:00',
                'ends_at' => '2026-06-20 10:00:00',
                'duration_minutes' => 60,
                'created_by' => $this->admin->id,
            ]);

            $this->actingAs($this->admin)
                ->patchJson("/api/company/measures/{$measure->id}", [$clearedField => null])
                ->assertStatus(200)
                ->assertJsonPath('data.durationMinutes', null);

            $this->assertDatabaseHas('measures', [
                'id' => $measure->id,
                'duration_minutes' => null,
            ]);
        }
    }

    public function test_status_only_update_on_completed_or_dismissed_scheduled_measure_returns_transition_error(): void
    {
        foreach (['COMPLETED', 'DISMISSED'] as $status) {
            $measure = Measure::factory()->create([
                'company_id' => $this->company->id,
                'team_id' => null,
                'status' => $status,
                'execution_type' => 'EVENT_PARTICIPATION',
                'starts_at' => '2026-06-20 09:00:00',
                'ends_at' => '2026-06-20 10:00:00',
                'duration_minutes' => 60,
                'created_by' => $this->admin->id,
            ]);

            $this->actingAs($this->admin)
                ->patchJson("/api/company/measures/{$measure->id}", ['status' => 'ACTIVE'])
                ->assertStatus(400)
                ->assertJson(['error' => 'invalid_transition']);
        }
    }

    public function test_completed_and_dismissed_measures_reject_mutable_edits(): void
    {
        foreach (['COMPLETED', 'DISMISSED'] as $status) {
            $measure = Measure::factory()->create([
                'company_id' => $this->company->id,
                'team_id' => null,
                'status' => $status,
                'created_by' => $this->admin->id,
            ]);

            $this->actingAs($this->admin)
                ->patchJson("/api/company/measures/{$measure->id}", ['title' => 'Should not change'])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['status']);
        }
    }

    public function test_measure_update_preserves_company_and_manager_scope(): void
    {
        $otherTeam = Team::factory()->create(['company_id' => $this->company->id]);
        $otherTeamMeasure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => $otherTeam->id,
            'status' => 'ACTIVE',
            'created_by' => $this->admin->id,
        ]);
        $managedMeasure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'status' => 'ACTIVE',
            'created_by' => $this->admin->id,
        ]);
        $foreignMeasure = Measure::factory()->create([
            'company_id' => Company::factory()->create()->id,
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($this->manager)
            ->patchJson("/api/company/measures/{$otherTeamMeasure->id}", ['title' => 'Out of scope'])
            ->assertStatus(403);

        $this->actingAs($this->manager)
            ->patchJson("/api/company/measures/{$managedMeasure->id}", ['title' => 'Managed update'])
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'Managed update');

        $this->actingAs($this->admin)
            ->patchJson("/api/company/measures/{$foreignMeasure->id}", ['title' => 'Foreign update'])
            ->assertStatus(404);
    }

    public function test_measure_creation_allows_qr_code_verification_requirement(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/company/measures', [
            'title' => 'QR measure',
            'category' => 'sport',
            'description' => 'A measure with QR verification.',
            'verificationRequirement' => 'QR_CODE',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.verificationRequirement', 'QR_CODE');

        $this->assertDatabaseHas('measures', [
            'id' => $response->json('data.id'),
            'verification_requirement' => 'QR_CODE',
        ]);
    }

    public function test_measure_creation_rejects_unsupported_verification_requirements(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/company/measures', [
            'title' => 'Unsupported measure',
            'category' => 'sport',
            'description' => 'A measure with unsupported verification.',
            'verificationRequirement' => 'NONE',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['verificationRequirement']);
    }

    public function test_measure_update_allows_qr_code_verification_requirement(): void
    {
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->patchJson("/api/company/measures/{$measure->id}", [
            'verificationRequirement' => 'QR_CODE',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.verificationRequirement', 'QR_CODE');

        $this->assertDatabaseHas('measures', [
            'id' => $measure->id,
            'verification_requirement' => 'QR_CODE',
        ]);

        $this->actingAs($this->admin)->patchJson("/api/company/measures/{$measure->id}", [
            'verificationRequirement' => 'ADMIN_CONFIRMATION',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['verificationRequirement']);
    }

    public function test_measure_update_rejects_verification_requirement_change_after_participation_exists(): void
    {
        $employee = User::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'role' => Role::EMPLOYEE,
        ]);
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'verification_requirement' => Measure::VERIFICATION_REQUIREMENT_SELF_REPORT,
            'created_by' => $this->admin->id,
        ]);
        MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $employee->id,
            'company_id' => $this->company->id,
            'team_id' => null,
        ]);

        $this->actingAs($this->admin)
            ->patchJson("/api/company/measures/{$measure->id}", [
                'verificationRequirement' => Measure::VERIFICATION_REQUIREMENT_QR_CODE,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['verificationRequirement']);
    }

    public function test_measure_update_rejects_unsupported_verification_requirements(): void
    {
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->patchJson("/api/company/measures/{$measure->id}", [
            'verificationRequirement' => 'PARTNER_CONFIRMATION',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['verificationRequirement']);
    }

    public function test_measure_update_does_not_allow_client_to_change_origin(): void
    {
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'measure_origin' => 'COMPANY_CREATED',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->patchJson("/api/company/measures/{$measure->id}", [
            'measureOrigin' => 'ELYO_TEMPLATE',
            'deliveryType' => 'REMOTE',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.measureOrigin', 'COMPANY_CREATED')
            ->assertJsonPath('data.deliveryType', 'REMOTE');

        $this->assertDatabaseHas('measures', [
            'id' => $measure->id,
            'measure_origin' => 'COMPANY_CREATED',
            'delivery_type' => 'REMOTE',
        ]);
    }

    public function test_measure_update_rejects_partial_ends_at_before_existing_starts_at(): void
    {
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'starts_at' => '2026-06-20 09:00:00',
            'ends_at' => '2026-06-20 10:00:00',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->patchJson("/api/company/measures/{$measure->id}", ['endsAt' => '2026-06-20 08:59:00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['endsAt']);
    }

    public function test_measure_update_rejects_partial_ends_at_equal_to_existing_starts_at(): void
    {
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'starts_at' => '2026-06-20 09:00:00',
            'ends_at' => '2026-06-20 10:00:00',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->patchJson("/api/company/measures/{$measure->id}", ['endsAt' => '2026-06-20 09:00:00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['endsAt']);
    }

    public function test_measure_update_rejects_partial_starts_at_after_existing_ends_at(): void
    {
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'starts_at' => '2026-06-20 09:00:00',
            'ends_at' => '2026-06-20 10:00:00',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->patchJson("/api/company/measures/{$measure->id}", ['startsAt' => '2026-06-20 10:01:00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['endsAt']);
    }

    public function test_manager_patch_outside_managed_team_returns_403_before_date_validation(): void
    {
        $otherTeam = Team::factory()->create(['company_id' => $this->company->id]);
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => $otherTeam->id,
            'status' => 'ACTIVE',
            'starts_at' => '2026-06-20 09:00:00',
            'ends_at' => '2026-06-20 10:00:00',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->manager)
            ->patchJson("/api/company/measures/{$measure->id}", ['endsAt' => '2026-06-20 08:59:00'])
            ->assertStatus(403);
    }

    public function test_foreign_company_patch_returns_404_before_date_validation(): void
    {
        $foreignMeasure = Measure::factory()->create([
            'company_id' => Company::factory()->create()->id,
            'status' => 'ACTIVE',
            'starts_at' => '2026-06-20 09:00:00',
            'ends_at' => '2026-06-20 10:00:00',
        ]);

        $this->actingAs($this->admin)
            ->patchJson("/api/company/measures/{$foreignMeasure->id}", ['endsAt' => '2026-06-20 08:59:00'])
            ->assertStatus(404);
    }

    public function test_in_scope_manager_patch_with_invalid_partial_date_returns_422(): void
    {
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'status' => 'ACTIVE',
            'starts_at' => '2026-06-20 09:00:00',
            'ends_at' => '2026-06-20 10:00:00',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->manager)
            ->patchJson("/api/company/measures/{$measure->id}", ['endsAt' => '2026-06-20 08:59:00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['endsAt']);
    }

    public function test_measure_update_accepts_valid_partial_date_range(): void
    {
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'starts_at' => '2026-06-20 09:00:00',
            'ends_at' => '2026-06-20 10:00:00',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->patchJson("/api/company/measures/{$measure->id}", ['endsAt' => '2026-06-20 11:00:00'])
            ->assertStatus(200)
            ->assertJsonPath('data.endsAt', '2026-06-20T11:00:00.000000Z');

        $this->assertDatabaseHas('measures', [
            'id' => $measure->id,
            'ends_at' => '2026-06-20 11:00:00',
        ]);
    }

    public function test_measure_schedule_accepts_explicit_timezone_offsets_and_stores_utc(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/company/measures', [
            'title' => 'Offset measure',
            'category' => 'sport',
            'description' => 'A measure created with timezone offsets.',
            'startsAt' => '2026-06-20T11:00:00+02:00',
            'endsAt' => '2026-06-20T12:30:00+02:00',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.startsAt', '2026-06-20T09:00:00.000000Z')
            ->assertJsonPath('data.endsAt', '2026-06-20T10:30:00.000000Z')
            ->assertJsonPath('data.durationMinutes', 90);

        $this->actingAs($this->admin)
            ->patchJson("/api/company/measures/{$response->json('data.id')}", [
                'endsAt' => '2026-06-20T11:00:00Z',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.endsAt', '2026-06-20T11:00:00.000000Z')
            ->assertJsonPath('data.durationMinutes', 120);

        $this->actingAs($this->admin)
            ->patchJson("/api/company/measures/{$response->json('data.id')}", [
                'startsAt' => '2026-06-20T10:00:00+02:00',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.startsAt', '2026-06-20T08:00:00.000000Z')
            ->assertJsonPath('data.endsAt', '2026-06-20T11:00:00.000000Z')
            ->assertJsonPath('data.durationMinutes', 180);
    }

    public function test_measure_domain_field_validation_rejects_invalid_values(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/company/measures', [
            'title' => 'Invalid measure',
            'category' => 'sport',
            'description' => 'A measure with invalid domain fields.',
            'deliveryType' => 'SELF_GUIDED',
            'executionType' => 'ONE_TIME',
            'verificationRequirement' => 'UNKNOWN',
            'startsAt' => '2026-06-20 10:00:00',
            'endsAt' => '2026-06-20 09:00:00',
            'durationMinutes' => 0,
            'capacity' => 0,
            'pointsOverride' => -1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'deliveryType',
                'executionType',
                'verificationRequirement',
                'endsAt',
                'durationMinutes',
                'capacity',
                'pointsOverride',
            ]);
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

    public function test_team_list_returns_empty_collection_when_team_layer_is_disabled(): void
    {
        $this->company->update(['team_layer_enabled' => false]);

        Team::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Hidden Team',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/company/teams');

        $response->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonMissing(['name' => 'Hidden Team']);
    }

    public function test_team_management_endpoints_are_rejected_when_team_layer_is_disabled(): void
    {
        $this->company->update(['team_layer_enabled' => false]);

        $this->actingAs($this->admin)
            ->postJson('/api/company/teams', [
                'name' => 'Blocked Team',
                'description' => 'Should not be created.',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TEAM_LAYER_DISABLED');

        $this->actingAs($this->admin)
            ->putJson("/api/company/teams/{$this->team->id}", [
                'name' => 'Blocked Rename',
                'description' => 'Should not be updated.',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TEAM_LAYER_DISABLED');

        $this->actingAs($this->admin)
            ->getJson("/api/company/teams/{$this->team->id}")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TEAM_LAYER_DISABLED');

        $this->actingAs($this->admin)
            ->deleteJson("/api/company/teams/{$this->team->id}")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TEAM_LAYER_DISABLED');

        $this->actingAs($this->admin)
            ->getJson("/api/company/teams/{$this->team->id}/members")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TEAM_LAYER_DISABLED');
    }

    public function test_company_dashboard_still_works_without_team_metadata_when_team_layer_disabled(): void
    {
        $this->company->update(['team_layer_enabled' => false]);

        $employees = User::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'role' => Role::EMPLOYEE,
        ]);

        foreach ($employees as $employee) {
            WellbeingEntry::factory()->create([
                'user_id' => $employee->id,
                'company_id' => $this->company->id,
                'score' => 7.0,
            ]);
        }

        $response = $this->actingAs($this->admin)->getJson('/api/company/dashboard');

        $response->assertStatus(200);
        $response->assertJsonPath('company.isAboveThreshold', true);
        $response->assertJsonPath('company.responseCount', 3);
        $response->assertJsonCount(0, 'teams');
    }

    public function test_team_scoped_reporting_is_rejected_when_team_layer_disabled(): void
    {
        $this->company->update(['team_layer_enabled' => false]);

        $this->actingAs($this->admin)
            ->getJson("/api/company/reports?teamId={$this->team->id}")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TEAM_LAYER_DISABLED');

        $this->actingAs($this->admin)
            ->getJson('/api/company/reports')
            ->assertStatus(200);

    }

    public function test_manager_only_user_without_team_layer_cannot_access_company_dashboard(): void
    {
        $this->company->update(['team_layer_enabled' => false]);

        $this->actingAs($this->manager)
            ->getJson('/api/company/dashboard')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'PORTAL_FORBIDDEN');

        $this->actingAs($this->admin)
            ->getJson('/api/company/dashboard')
            ->assertOk();
    }

    public function test_manager_only_user_without_team_layer_cannot_access_company_surveys(): void
    {
        $this->company->update(['team_layer_enabled' => false]);

        $this->actingAs($this->manager)
            ->getJson('/api/company/surveys')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'PORTAL_FORBIDDEN');
    }

    public function test_manager_only_user_with_team_layer_can_access_company_dashboard(): void
    {
        $this->actingAs($this->manager)
            ->getJson('/api/company/dashboard')
            ->assertOk();
    }

    public function test_plain_employee_cannot_access_company_routes(): void
    {
        $employee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => Role::EMPLOYEE,
        ]);

        $this->actingAs($employee)
            ->getJson('/api/company/dashboard')
            ->assertStatus(403);

        $this->actingAs($employee)
            ->getJson('/api/company/surveys')
            ->assertStatus(403);
    }

    public function test_manager_employee_user_without_team_layer_can_still_access_employee_dashboard(): void
    {
        $this->company->update(['team_layer_enabled' => false]);
        UserRole::create(['user_id' => $this->manager->id, 'role' => Role::EMPLOYEE]);

        $this->actingAs($this->manager)
            ->getJson('/api/employee/dashboard')
            ->assertOk();
    }

    public function test_survey_team_targeting_is_rejected_when_team_layer_disabled(): void
    {
        $this->company->update(['team_layer_enabled' => false]);

        $payload = [
            'title' => 'Team pulse',
            'description' => 'Quarterly employee survey.',
            'teamIds' => [$this->team->id],
            'questions' => [
                [
                    'text' => 'How balanced is your workload?',
                    'type' => 'SCALE',
                    'order' => 0,
                ],
            ],
        ];

        $this->actingAs($this->admin)
            ->postJson('/api/company/surveys', $payload)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'TEAM_LAYER_DISABLED');

        $survey = Survey::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'status' => 'DRAFT',
        ]);

        $this->actingAs($this->admin)
            ->patchJson("/api/company/surveys/{$survey->id}", ['teamIds' => [$this->team->id]])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'TEAM_LAYER_DISABLED');

        $this->actingAs($this->admin)
            ->postJson('/api/company/surveys', array_merge($payload, ['teamIds' => []]))
            ->assertStatus(201)
            ->assertJsonPath('data.teamIds', []);
    }

    public function test_company_wide_survey_results_still_work_when_team_layer_disabled(): void
    {
        $this->company->update(['team_layer_enabled' => false]);
        $survey = Survey::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'status' => 'ACTIVE',
        ]);
        $question = SurveyQuestion::factory()->create([
            'survey_id' => $survey->id,
            'type' => QuestionType::SCALE,
        ]);
        $employees = User::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'role' => Role::EMPLOYEE,
        ]);

        foreach ($employees as $employee) {
            $this->createSurveyAnswer($survey, $question, $employee, ['scale_value' => 4]);
        }

        $response = $this->actingAs($this->admin)->getJson("/api/company/surveys/{$survey->id}/results");

        $response->assertStatus(200);
        $response->assertJsonPath('data.scope.type', 'company');
        $response->assertJsonPath('data.scope.teamIds', null);
        $response->assertJsonPath('data.participation.responseCount', 3);
    }

    public function test_team_scoped_survey_results_are_rejected_when_team_layer_disabled(): void
    {
        $this->company->update(['team_layer_enabled' => false]);
        $survey = Survey::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'status' => 'ACTIVE',
        ]);
        $survey->teams()->sync([$this->team->id]);

        $this->actingAs($this->admin)
            ->getJson("/api/company/surveys/{$survey->id}/results")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TEAM_LAYER_DISABLED');
    }

    public function test_measure_team_targeting_is_rejected_when_team_layer_disabled(): void
    {
        $this->company->update(['team_layer_enabled' => false]);

        $this->actingAs($this->admin)
            ->postJson('/api/company/measures', [
                'title' => 'Team workshop',
                'category' => 'workshop',
                'description' => 'A focused workshop for one team.',
                'teamId' => $this->team->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'TEAM_LAYER_DISABLED');

        $this->actingAs($this->admin)
            ->postJson('/api/company/measures', [
                'title' => 'Company workshop',
                'category' => 'workshop',
                'description' => 'A company-wide workshop for employees.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.team', null);

        $globalMeasure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'title' => 'Global measure',
            'created_by' => $this->admin->id,
        ]);
        Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'title' => 'Team measure',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/company/measures');

        $response->assertStatus(200);
        $this->assertEqualsCanonicalizing(
            ['Company workshop', $globalMeasure->title],
            collect($response->json('data'))->pluck('title')->all()
        );
    }

    public function test_team_scoped_measure_update_is_rejected_when_team_layer_disabled(): void
    {
        $this->company->update(['team_layer_enabled' => false]);

        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'title' => 'Existing team measure',
            'created_by' => $this->admin->id,
            'status' => 'SUGGESTED',
        ]);

        $this->actingAs($this->admin)
            ->patchJson("/api/company/measures/{$measure->id}", ['status' => 'ACTIVE'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TEAM_LAYER_DISABLED');
    }

    public function test_company_survey_show_is_rejected_when_team_layer_disabled_and_survey_is_team_scoped(): void
    {
        $this->company->update(['team_layer_enabled' => false]);

        $survey = Survey::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'status' => 'DRAFT',
        ]);
        $survey->teams()->sync([$this->team->id]);

        $this->actingAs($this->admin)
            ->getJson("/api/company/surveys/{$survey->id}")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TEAM_LAYER_DISABLED');
    }

    public function test_team_members_endpoint_returns_only_matching_team_and_company_users(): void
    {
        $otherCompany = Company::factory()->create();
        $otherTeam = Team::factory()->create(['company_id' => $this->company->id, 'manager_id' => null]);
        $matchingMember = User::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'role' => Role::EMPLOYEE,
            'email' => 'matching-member@test.com',
        ]);
        $sameCompanyOtherTeam = User::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => $otherTeam->id,
            'role' => Role::EMPLOYEE,
            'email' => 'other-team-member@test.com',
        ]);
        $foreignCompanySameTeamId = User::factory()->create([
            'company_id' => $otherCompany->id,
            'team_id' => $this->team->id,
            'role' => Role::EMPLOYEE,
            'email' => 'foreign-company-member@test.com',
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/company/teams/{$this->team->id}/members");

        $response->assertStatus(200);
        $emails = collect($response->json('members'))->pluck('email')->all();
        $this->assertContains($matchingMember->email, $emails);
        $this->assertNotContains($sameCompanyOtherTeam->email, $emails);
        $this->assertNotContains($foreignCompanySameTeamId->email, $emails);
        $response->assertJsonMissingPath('members.0.company_id');
        $response->assertJsonMissingPath('members.0.team_id');
    }

    public function test_manager_can_view_only_managed_team_directory_members_without_health_data(): void
    {
        $otherCompany = Company::factory()->create();
        $managedMember = User::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'role' => Role::EMPLOYEE,
            'email' => 'managed-team-member@test.com',
            'last_login_at' => now(),
        ]);
        $sameCompanyUnassigned = User::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'role' => Role::EMPLOYEE,
            'email' => 'unassigned-member@test.com',
        ]);
        $foreignCompanyMember = User::factory()->create([
            'company_id' => $otherCompany->id,
            'team_id' => $this->team->id,
            'role' => Role::EMPLOYEE,
            'email' => 'foreign-managed-member@test.com',
        ]);
        WellbeingEntry::factory()->create([
            'user_id' => $managedMember->id,
            'company_id' => $this->company->id,
            'mood' => 2,
            'stress' => 9,
            'energy' => 3,
            'score' => 2.3,
            'note' => 'Private health note',
        ]);

        $response = $this->actingAs($this->manager)->getJson("/api/company/teams/{$this->team->id}/members");

        $response->assertStatus(200);
        $members = collect($response->json('members'));
        $emails = $members->pluck('email')->all();
        $this->assertContains($managedMember->email, $emails);
        $this->assertNotContains($sameCompanyUnassigned->email, $emails);
        $this->assertNotContains($foreignCompanyMember->email, $emails);

        $member = $members->firstWhere('email', $managedMember->email);
        $this->assertNotNull($member);
        $this->assertSame(['email', 'id', 'name', 'roles', 'status'], collect($member)->keys()->sort()->values()->all());
        $response->assertJsonMissingPath('members.0.lastLoginAt');
        $response->assertJsonMissingPath('members.0.mood');
        $response->assertJsonMissingPath('members.0.stress');
        $response->assertJsonMissingPath('members.0.energy');
        $response->assertJsonMissingPath('members.0.score');
        $response->assertJsonMissingPath('members.0.note');
    }

    public function test_manager_cannot_view_unmanaged_team_members(): void
    {
        $unmanagedTeam = Team::factory()->create(['company_id' => $this->company->id, 'manager_id' => null]);

        $this->actingAs($this->manager)
            ->getJson("/api/company/teams/{$unmanagedTeam->id}/members")
            ->assertStatus(403);
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
