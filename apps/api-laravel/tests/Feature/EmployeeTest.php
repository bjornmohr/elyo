<?php

namespace Tests\Feature;

use App\Enums\QuestionType;
use App\Enums\Role;
use App\Enums\SurveyStatus;
use App\Models\Company;
use App\Models\Measure;
use App\Models\MeasureCheckinToken;
use App\Models\MeasureParticipation;
use App\Models\PointTransaction;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPoints;
use App\Models\WellbeingEntry;
use App\Services\WellbeingService;
use App\Services\MeasureCheckinTokenService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    protected User $employee;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->employee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => Role::EMPLOYEE,
        ]);
    }

    public function test_employee_can_get_dashboard_data()
    {
        WellbeingEntry::factory()->create([
            'user_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'period_key' => '2024-W01',
            'score' => 7.5,
        ]);

        $response = $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'latest',
                'entries',
                'streakCount',
            ]);
    }

    public function test_employee_can_submit_checkin()
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));

        $response = $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/employee/checkin', [
                'mood' => 8,
                'stress' => 3,
                'energy' => 7,
                'note' => 'Feeling good',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure(['score', 'periodKey']);

        $this->assertDatabaseHas('wellbeing_entries', [
            'user_id' => $this->employee->id,
            'period_key' => '2026-05-25',
            'mood' => 8,
            'stress' => 3,
            'energy' => 7,
        ]);
    }

    public function test_employee_can_submit_checkin_only_once_per_day()
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));

        $payload = [
            'mood' => 8,
            'stress' => 3,
            'energy' => 7,
        ];

        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/employee/checkin', $payload)
            ->assertStatus(200);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/employee/checkin', $payload)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CHECKIN_ALREADY_DONE');

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/checkin/status')
            ->assertStatus(200)
            ->assertJsonPath('completed', true);
    }

    public function test_existing_daily_wellbeing_entry_blocks_new_checkin()
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));
        $this->createDailyWellbeingEntry($this->employee, '2026-05-25');

        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/employee/checkin', [
                'mood' => 8,
                'stress' => 3,
                'energy' => 7,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CHECKIN_ALREADY_DONE');

        $this->assertSame(1, WellbeingEntry::where('user_id', $this->employee->id)
            ->where('period_key', '2026-05-25')
            ->count());
    }

    public function test_rejected_duplicate_checkin_does_not_award_points_or_update_streak()
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));
        $this->createDailyWellbeingEntry($this->employee, '2026-05-25');
        UserPoints::create([
            'user_id' => $this->employee->id,
            'total' => 99,
            'streak' => 5,
            'last_checkin' => Carbon::parse('2026-05-24 09:00:00'),
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/employee/checkin', [
                'mood' => 8,
                'stress' => 3,
                'energy' => 7,
            ])
            ->assertStatus(409);

        $this->assertDatabaseMissing('point_transactions', [
            'user_id' => $this->employee->id,
            'reason' => 'daily_checkin',
        ]);
        $this->assertDatabaseHas('user_points', [
            'user_id' => $this->employee->id,
            'total' => 99,
            'streak' => 5,
        ]);
    }

    public function test_submit_checkin_returns_null_when_database_unique_constraint_is_hit()
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));
        $this->createDailyWellbeingEntry($this->employee, '2026-05-25');

        $service = new class extends WellbeingService
        {
            public function hasDailyCheckin(User $user, ?string $periodKey = null): bool
            {
                return false;
            }
        };

        DB::beginTransaction();

        try {
            $result = $service->submitCheckin($this->employee, [
                'mood' => 8,
                'stress' => 3,
                'energy' => 7,
            ]);
        } finally {
            DB::rollBack();
        }

        $this->assertNull($result);
        $this->assertSame(1, WellbeingEntry::where([
            'user_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'period_key' => '2026-05-25',
        ])->count());
    }

    public function test_daily_streak_is_one_when_only_today_exists()
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));
        $this->createDailyWellbeingEntry($this->employee, '2026-05-25');

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('streakCount', 1);
    }

    public function test_workday_streak_counts_friday_and_monday_as_consecutive()
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));
        $this->createDailyWellbeingEntry($this->employee, '2026-05-25');
        $this->createDailyWellbeingEntry($this->employee, '2026-05-22');

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('streakCount', 2);
    }

    public function test_workday_streak_counts_thursday_friday_and_monday()
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));
        $this->createDailyWellbeingEntry($this->employee, '2026-05-25');
        $this->createDailyWellbeingEntry($this->employee, '2026-05-22');
        $this->createDailyWellbeingEntry($this->employee, '2026-05-21');

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('streakCount', 3);
    }

    public function test_workday_streak_is_one_when_friday_is_missing()
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));
        $this->createDailyWellbeingEntry($this->employee, '2026-05-25');
        $this->createDailyWellbeingEntry($this->employee, '2026-05-21');

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('streakCount', 1);
    }

    public function test_weekend_entries_do_not_extend_workday_streak()
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));
        $this->createDailyWellbeingEntry($this->employee, '2026-05-25');
        $this->createDailyWellbeingEntry($this->employee, '2026-05-24');
        $this->createDailyWellbeingEntry($this->employee, '2026-05-23');
        $this->createDailyWellbeingEntry($this->employee, '2026-05-22');

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('streakCount', 2);
    }

    public function test_weekend_gaps_do_not_break_workday_streak()
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));
        $this->createDailyWellbeingEntry($this->employee, '2026-05-25');
        $this->createDailyWellbeingEntry($this->employee, '2026-05-22');

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('streakCount', 2);
    }

    public function test_malformed_daily_period_keys_do_not_affect_workday_streak()
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));
        $this->createDailyWellbeingEntry($this->employee, '2026-99-99');
        $this->createDailyWellbeingEntry($this->employee, '2026-02-31');
        $this->createDailyWellbeingEntry($this->employee, '2026-00-10');
        $this->createDailyWellbeingEntry($this->employee, '2026-05-25');
        $this->createDailyWellbeingEntry($this->employee, '2026-05-22');

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('streakCount', 2);
    }

    public function test_weekend_only_checkins_do_not_produce_workday_streak()
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));
        $this->createDailyWellbeingEntry($this->employee, '2026-05-24');
        $this->createDailyWellbeingEntry($this->employee, '2026-05-23');

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('streakCount', 0);
    }

    public function test_daily_streak_uses_seeded_wellbeing_entries_without_point_transactions()
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));
        $this->createDailyWellbeingEntry($this->employee, '2026-05-25');
        $this->createDailyWellbeingEntry($this->employee, '2026-05-22');
        $this->createDailyWellbeingEntry($this->employee, '2026-05-21');

        $this->assertSame(0, PointTransaction::where('user_id', $this->employee->id)->count());

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('streakCount', 3);
    }

    public function test_streak_7days_milestone_is_awarded_when_user_reaches_seven_workdays()
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));
        $this->createPreviousWorkdayEntries($this->employee, '2026-05-25', 6);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/employee/checkin', [
                'mood' => 8,
                'stress' => 3,
                'energy' => 7,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $this->employee->id,
            'reason' => 'daily_checkin',
            'points' => 10,
        ]);
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $this->employee->id,
            'reason' => 'streak_7days',
            'points' => 50,
        ]);
        $this->assertDatabaseHas('user_points', [
            'user_id' => $this->employee->id,
            'total' => 60,
            'streak' => 7,
        ]);
    }

    public function test_streak_7days_milestone_is_not_awarded_again_for_same_user()
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));
        $this->createPreviousWorkdayEntries($this->employee, '2026-05-25', 6);
        PointTransaction::create([
            'user_id' => $this->employee->id,
            'reason' => 'streak_7days',
            'points' => 50,
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/employee/checkin', [
                'mood' => 8,
                'stress' => 3,
                'energy' => 7,
            ])
            ->assertStatus(200);

        $this->assertSame(1, PointTransaction::where('user_id', $this->employee->id)
            ->where('reason', 'streak_7days')
            ->count());
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $this->employee->id,
            'reason' => 'daily_checkin',
            'points' => 10,
        ]);
    }

    public function test_streak_30days_milestone_is_not_awarded_again_for_same_user()
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));
        $this->createPreviousWorkdayEntries($this->employee, '2026-05-25', 29);
        PointTransaction::create([
            'user_id' => $this->employee->id,
            'reason' => 'streak_30days',
            'points' => 200,
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/employee/checkin', [
                'mood' => 8,
                'stress' => 3,
                'energy' => 7,
            ])
            ->assertStatus(200);

        $this->assertSame(1, PointTransaction::where('user_id', $this->employee->id)
            ->where('reason', 'streak_30days')
            ->count());
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $this->employee->id,
            'reason' => 'daily_checkin',
            'points' => 10,
        ]);
    }

    public function test_another_users_milestone_transaction_does_not_block_current_user()
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));
        $otherUser = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => Role::EMPLOYEE,
        ]);
        PointTransaction::create([
            'user_id' => $otherUser->id,
            'reason' => 'streak_7days',
            'points' => 50,
        ]);
        $this->createPreviousWorkdayEntries($this->employee, '2026-05-25', 6);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/employee/checkin', [
                'mood' => 8,
                'stress' => 3,
                'energy' => 7,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $this->employee->id,
            'reason' => 'streak_7days',
            'points' => 50,
        ]);
    }

    public function test_daily_streak_ignores_entries_from_another_user()
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));
        $otherUser = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => Role::EMPLOYEE,
        ]);
        $this->createDailyWellbeingEntry($this->employee, '2026-05-25');
        $this->createDailyWellbeingEntry($otherUser, '2026-05-24');
        $this->createDailyWellbeingEntry($otherUser, '2026-05-23');

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('streakCount', 1);
    }

    public function test_daily_streak_ignores_entries_from_another_company()
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));
        $otherCompany = Company::factory()->create();
        $this->createDailyWellbeingEntry($this->employee, '2026-05-25');
        $this->createDailyWellbeingEntry($this->employee, '2026-05-24', $otherCompany);
        $this->createDailyWellbeingEntry($this->employee, '2026-05-23', $otherCompany);

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('streakCount', 1);
    }

    public function test_employee_can_get_history()
    {
        WellbeingEntry::factory()->create([
            'user_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'period_key' => '2024-W01',
        ]);
        WellbeingEntry::factory()->create([
            'user_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'period_key' => '2024-W02',
        ]);

        $response = $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/history?limit=10');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'entries');
    }

    public function test_employee_can_update_profile()
    {
        $response = $this->actingAs($this->employee, 'sanctum')
            ->putJson('/api/employee/profile', [
                'name' => 'Updated Name',
                'birthYear' => 1990,
                'biologicalSex' => 'PREFER_NOT_TO_SAY',
                'activityLevel' => 'MEDIUM',
                'sleepQuality' => 'GOOD',
                'stressTendency' => 'LOW',
                'smokingStatus' => 'NEVER',
                'nutritionType' => 'balanced',
                'hasMedication' => false,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.anamnesis.birthYear', 1990);

        $this->assertEquals('Updated Name', $this->employee->refresh()->name);
        $this->assertDatabaseHas('anamnesis_profiles', [
            'user_id' => $this->employee->id,
            'birth_year' => 1990,
        ]);
    }

    public function test_employee_can_list_surveys()
    {
        $survey = Survey::create([
            'company_id' => $this->company->id,
            'title' => 'Test Survey',
            'status' => SurveyStatus::ACTIVE,
        ]);

        $response = $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/surveys');

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Test Survey']);
    }

    public function test_employee_can_get_survey_details()
    {
        $survey = Survey::create([
            'company_id' => $this->company->id,
            'title' => 'Test Survey',
            'status' => SurveyStatus::ACTIVE,
        ]);

        SurveyQuestion::create([
            'survey_id' => $survey->id,
            'text' => 'How are you?',
            'type' => QuestionType::SCALE,
            'order' => 1,
        ]);

        $response = $this->actingAs($this->employee, 'sanctum')
            ->getJson("/api/employee/surveys/{$survey->id}");

        $response->assertStatus(200)
            ->assertJsonPath('survey.title', 'Test Survey')
            ->assertJsonCount(1, 'survey.questions');
    }

    public function test_employee_can_respond_to_survey()
    {
        $survey = Survey::create([
            'company_id' => $this->company->id,
            'title' => 'Test Survey',
            'status' => SurveyStatus::ACTIVE,
        ]);

        $question = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'text' => 'How are you?',
            'type' => QuestionType::SCALE,
            'order' => 1,
        ]);

        $response = $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/employee/surveys/{$survey->id}/respond", [
                'answers' => [
                    [
                        'questionId' => $question->id,
                        'scaleValue' => 8,
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('survey_responses', [
            'user_id' => $this->employee->id,
            'survey_id' => $survey->id,
        ]);
    }

    public function test_employee_team_targeted_surveys_are_not_available_when_team_layer_is_disabled(): void
    {
        $this->company->update(['team_layer_enabled' => false]);
        $team = Team::factory()->create(['company_id' => $this->company->id]);
        $this->employee->update(['team_id' => $team->id]);

        $companySurvey = Survey::create([
            'company_id' => $this->company->id,
            'title' => 'Company Survey',
            'status' => SurveyStatus::ACTIVE,
        ]);
        $companyQuestion = SurveyQuestion::create([
            'survey_id' => $companySurvey->id,
            'text' => 'How are you?',
            'type' => QuestionType::SCALE,
            'order' => 1,
        ]);

        $teamSurvey = Survey::create([
            'company_id' => $this->company->id,
            'title' => 'Team Survey',
            'status' => SurveyStatus::ACTIVE,
        ]);
        $teamSurvey->teams()->sync([$team->id]);
        $teamQuestion = SurveyQuestion::create([
            'survey_id' => $teamSurvey->id,
            'text' => 'How is the team?',
            'type' => QuestionType::SCALE,
            'order' => 1,
        ]);

        $listResponse = $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/surveys');

        $listResponse->assertStatus(200)
            ->assertJsonFragment(['title' => 'Company Survey'])
            ->assertJsonMissing(['title' => 'Team Survey']);

        $this->actingAs($this->employee, 'sanctum')
            ->getJson("/api/employee/surveys/{$teamSurvey->id}")
            ->assertStatus(404);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/employee/surveys/{$teamSurvey->id}/respond", [
                'answers' => [
                    [
                        'questionId' => $teamQuestion->id,
                        'scaleValue' => 8,
                    ],
                ],
            ])
            ->assertStatus(404);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/employee/surveys/{$companySurvey->id}/respond", [
                'answers' => [
                    [
                        'questionId' => $companyQuestion->id,
                        'scaleValue' => 7,
                    ],
                ],
            ])
            ->assertStatus(200)
            ->assertJson(['ok' => true]);
    }

    public function test_employee_can_always_view_own_survey_result()
    {
        $survey = Survey::create([
            'company_id' => $this->company->id,
            'title' => 'Result Survey',
            'status' => SurveyStatus::ACTIVE,
        ]);

        $question = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'text' => 'How are you?',
            'type' => QuestionType::SCALE,
            'order' => 1,
        ]);

        $surveyResponse = SurveyResponse::factory()->create([
            'survey_id' => $survey->id,
            'user_id' => $this->employee->id,
            'company_id' => $this->company->id,
        ]);
        SurveyAnswer::factory()->create([
            'response_id' => $surveyResponse->id,
            'question_id' => $question->id,
            'scale_value' => 8,
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->getJson("/api/employee/surveys/{$survey->id}/result")
            ->assertStatus(200)
            ->assertJsonPath('survey.title', 'Result Survey')
            ->assertJsonPath('survey.questions.0.answer.scaleValue', 8);
    }

    public function test_employee_own_result_accessible_for_team_scoped_survey_when_team_layer_disabled(): void
    {
        // The result() endpoint returns the employee's own previously-submitted answers.
        // This remains accessible even when the team layer is disabled because it exposes
        // the employee's own data only, not aggregated health data.
        $this->company->update(['team_layer_enabled' => false]);

        $team = Team::factory()->create(['company_id' => $this->company->id]);
        $survey = Survey::create([
            'company_id' => $this->company->id,
            'title' => 'Team Result Survey',
            'status' => SurveyStatus::ACTIVE,
        ]);
        $survey->teams()->sync([$team->id]);

        $question = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'text' => 'Team question?',
            'type' => QuestionType::SCALE,
            'order' => 1,
        ]);

        $surveyResponse = SurveyResponse::factory()->create([
            'survey_id' => $survey->id,
            'user_id' => $this->employee->id,
            'company_id' => $this->company->id,
        ]);
        SurveyAnswer::factory()->create([
            'response_id' => $surveyResponse->id,
            'question_id' => $question->id,
            'scale_value' => 7,
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->getJson("/api/employee/surveys/{$survey->id}/result")
            ->assertStatus(200)
            ->assertJsonPath('survey.title', 'Team Result Survey')
            ->assertJsonPath('survey.questions.0.answer.scaleValue', 7);
    }

    public function test_employee_can_list_relevant_measures()
    {
        $team = Team::factory()->create(['company_id' => $this->company->id]);
        $otherTeam = Team::factory()->create(['company_id' => $this->company->id]);
        $this->employee->update(['team_id' => $team->id]);

        Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'title' => 'Global measure',
            'status' => 'ACTIVE',
        ]);
        Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => $team->id,
            'title' => 'Team measure',
            'status' => 'ACTIVE',
        ]);
        Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => $otherTeam->id,
            'title' => 'Other team measure',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/measures');

        $response->assertStatus(200);
        $this->assertEqualsCanonicalizing(
            ['Global measure', 'Team measure'],
            collect($response->json('data'))->pluck('title')->all()
        );
    }

    public function test_employee_measure_list_exposes_measure_domain_fields(): void
    {
        Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'title' => 'Guided session',
            'status' => 'ACTIVE',
            'delivery_type' => 'HYBRID',
            'execution_type' => 'GUIDED_SESSION',
            'verification_requirement' => 'SELF_REPORT',
            'starts_at' => Carbon::parse('2026-06-20 09:00:00'),
            'ends_at' => Carbon::parse('2026-06-20 10:00:00'),
            'duration_minutes' => 60,
            'instructions' => 'Bring water.',
            'location_name' => 'Training Room A',
            'location_address' => 'Main Street 1',
            'capacity' => 15,
            'points_override' => 5,
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/measures')
            ->assertStatus(200)
            ->assertJsonPath('data.0.deliveryType', 'HYBRID')
            ->assertJsonPath('data.0.executionType', 'GUIDED_SESSION')
            ->assertJsonPath('data.0.verificationRequirement', 'SELF_REPORT')
            ->assertJsonPath('data.0.visibilityScope', 'COMPANY')
            ->assertJsonPath('data.0.durationMinutes', 60)
            ->assertJsonPath('data.0.instructions', 'Bring water.')
            ->assertJsonPath('data.0.locationName', 'Training Room A')
            ->assertJsonPath('data.0.locationAddress', 'Main Street 1')
            ->assertJsonPath('data.0.capacity', 15)
            ->assertJsonPath('data.0.pointsOverride', 5);
    }

    public function test_employee_measure_list_includes_authenticated_employee_participation_state()
    {
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'title' => 'Global measure',
            'status' => 'ACTIVE',
        ]);

        MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'team_id' => null,
            'participated_at' => Carbon::parse('2026-06-01 09:00:00'),
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/measures')
            ->assertStatus(200)
            ->assertJsonPath('data.0.participation.isParticipating', true)
            ->assertJsonPath('data.0.participation.participatedAt', '2026-06-01T09:00:00+00:00')
            ->assertJsonPath('data.0.participation.verificationType', 'SELF_REPORTED')
            ->assertJsonPath('data.0.participation.verifiedAt', '2026-06-01T09:00:00+00:00');
    }

    public function test_employee_measure_list_does_not_expose_other_users_participation_state()
    {
        $otherEmployee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => Role::EMPLOYEE,
        ]);
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'title' => 'Global measure',
            'status' => 'ACTIVE',
        ]);

        MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $otherEmployee->id,
            'company_id' => $this->company->id,
            'team_id' => null,
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/measures')
            ->assertStatus(200)
            ->assertJsonPath('data.0.participation.isParticipating', false)
            ->assertJsonPath('data.0.participation.participatedAt', null)
            ->assertJsonPath('data.0.participation.verificationType', null)
            ->assertJsonPath('data.0.participation.verifiedAt', null);
    }

    public function test_employee_can_participate_in_active_company_wide_measure()
    {
        $this->travelTo(Carbon::parse('2026-06-01 10:00:00'));

        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'verification_requirement' => 'SELF_REPORT',
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/employee/measures/{$measure->id}/participate")
            ->assertStatus(201)
            ->assertJsonPath('data.id', $measure->id)
            ->assertJsonPath('data.participation.isParticipating', true)
            ->assertJsonPath('data.participation.participatedAt', '2026-06-01T10:00:00+00:00')
            ->assertJsonPath('data.participation.verificationType', 'SELF_REPORTED')
            ->assertJsonPath('data.participation.verifiedAt', '2026-06-01T10:00:00+00:00');

        $this->assertDatabaseHas('measure_participations', [
            'measure_id' => $measure->id,
            'user_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'team_id' => null,
            'verification_type' => 'SELF_REPORTED',
            'verified_by_user_id' => null,
        ]);
        $participation = MeasureParticipation::query()
            ->where('measure_id', $measure->id)
            ->where('user_id', $this->employee->id)
            ->firstOrFail();
        $this->assertNotNull($participation->verified_at);
        $this->assertTrue($participation->participated_at->equalTo($participation->verified_at));
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $this->employee->id,
            'reason' => 'measure_participation',
            'points' => 20,
        ]);
        $this->assertDatabaseHas('user_points', [
            'user_id' => $this->employee->id,
            'total' => 20,
        ]);
    }

    public function test_employee_can_participate_in_active_team_measure_for_own_team()
    {
        $team = Team::factory()->create(['company_id' => $this->company->id]);
        $this->employee->update(['team_id' => $team->id]);
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => $team->id,
            'status' => 'ACTIVE',
            'verification_requirement' => 'SELF_REPORT',
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/employee/measures/{$measure->id}/participate")
            ->assertStatus(201)
            ->assertJsonPath('data.participation.isParticipating', true);

        $this->assertDatabaseHas('measure_participations', [
            'measure_id' => $measure->id,
            'user_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'team_id' => $team->id,
        ]);
    }

    public function test_employee_cannot_self_report_qr_required_measure(): void
    {
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'verification_requirement' => 'QR_CODE',
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/employee/measures/{$measure->id}/participate")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'MEASURE_REQUIRES_QR_CHECKIN');

        $this->assertDatabaseMissing('measure_participations', [
            'measure_id' => $measure->id,
            'user_id' => $this->employee->id,
        ]);
        $this->assertSame(0, PointTransaction::where('reason', 'measure_participation')->count());
    }

    public function test_employee_cannot_participate_in_measure_from_another_company()
    {
        $otherCompany = Company::factory()->create();
        $measure = Measure::factory()->create([
            'company_id' => $otherCompany->id,
            'team_id' => null,
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/employee/measures/{$measure->id}/participate")
            ->assertStatus(404);

        $this->assertDatabaseMissing('measure_participations', [
            'measure_id' => $measure->id,
            'user_id' => $this->employee->id,
        ]);
    }

    public function test_employee_cannot_participate_in_measure_for_another_team()
    {
        $team = Team::factory()->create(['company_id' => $this->company->id]);
        $otherTeam = Team::factory()->create(['company_id' => $this->company->id]);
        $this->employee->update(['team_id' => $team->id]);
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => $otherTeam->id,
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/employee/measures/{$measure->id}/participate")
            ->assertStatus(404);

        $this->assertDatabaseMissing('measure_participations', [
            'measure_id' => $measure->id,
            'user_id' => $this->employee->id,
        ]);
    }

    public function test_employee_cannot_participate_in_inactive_visible_measures()
    {
        foreach (['SUGGESTED', 'COMPLETED', 'DISMISSED'] as $status) {
            $measure = Measure::factory()->create([
                'company_id' => $this->company->id,
                'team_id' => null,
                'status' => $status,
            ]);

            $this->actingAs($this->employee, 'sanctum')
                ->postJson("/api/employee/measures/{$measure->id}/participate")
                ->assertStatus(409)
                ->assertJsonPath('error.code', 'MEASURE_NOT_ACTIVE');
        }

        $this->assertSame(0, MeasureParticipation::query()->count());
        $this->assertSame(0, PointTransaction::where('reason', 'measure_participation')->count());
    }

    public function test_duplicate_measure_participation_returns_conflict_and_does_not_award_points_twice()
    {
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'verification_requirement' => 'SELF_REPORT',
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/employee/measures/{$measure->id}/participate")
            ->assertStatus(201);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/employee/measures/{$measure->id}/participate")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'MEASURE_ALREADY_PARTICIPATED');

        $this->assertSame(1, MeasureParticipation::query()
            ->where('measure_id', $measure->id)
            ->where('user_id', $this->employee->id)
            ->count());
        $this->assertSame(1, PointTransaction::query()
            ->where('user_id', $this->employee->id)
            ->where('reason', 'measure_participation')
            ->count());
        $this->assertDatabaseHas('user_points', [
            'user_id' => $this->employee->id,
            'total' => 20,
        ]);
    }

    public function test_employee_can_redeem_measure_checkin_token_for_qr_participation(): void
    {
        $this->travelTo(Carbon::parse('2026-06-10 10:00:00'));
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'verification_requirement' => 'QR_CODE',
        ]);
        $token = $this->actingAs(User::factory()->create([
            'company_id' => $this->company->id,
            'role' => Role::COMPANY_ADMIN,
        ]), 'sanctum')
            ->postJson("/api/company/measures/{$measure->id}/checkin-token")
            ->assertStatus(201)
            ->json('data.token');

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/employee/measure-checkins/{$token}", [
                'user_id' => User::factory()->create()->id,
                'company_id' => Company::factory()->create()->id,
                'team_id' => Team::factory()->create()->id,
                'participated_at' => '2025-01-01T00:00:00Z',
                'verification_type' => 'SELF_REPORTED',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.id', $measure->id)
            ->assertJsonPath('data.participation.isParticipating', true)
            ->assertJsonPath('data.participation.participatedAt', '2026-06-10T10:00:00+00:00')
            ->assertJsonPath('data.participation.verificationType', 'QR_CHECKIN')
            ->assertJsonPath('data.participation.verifiedAt', '2026-06-10T10:00:00+00:00');

        $this->assertDatabaseHas('measure_participations', [
            'measure_id' => $measure->id,
            'user_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'team_id' => null,
            'verification_type' => 'QR_CHECKIN',
            'verified_by_user_id' => null,
        ]);
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $this->employee->id,
            'reason' => 'measure_participation',
            'points' => 20,
        ]);
        $this->assertNotNull(MeasureCheckinToken::where('token_hash', MeasureCheckinTokenService::hashToken($token))->value('last_used_at'));
    }

    public function test_employee_cannot_redeem_qr_checkin_for_self_report_measure(): void
    {
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'verification_requirement' => 'SELF_REPORT',
        ]);
        $token = bin2hex(random_bytes(32));
        MeasureCheckinToken::create([
            'measure_id' => $measure->id,
            'company_id' => $this->company->id,
            'token_hash' => MeasureCheckinTokenService::hashToken($token),
            'created_by_user_id' => User::factory()->create(['company_id' => $this->company->id])->id,
            'valid_from' => now(),
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/employee/measure-checkins/{$token}")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'MEASURE_DOES_NOT_ALLOW_QR_CHECKIN');

        $this->assertDatabaseMissing('measure_participations', [
            'measure_id' => $measure->id,
            'user_id' => $this->employee->id,
        ]);
        $this->assertSame(0, PointTransaction::where('reason', 'measure_participation')->count());
        $this->assertNull(MeasureCheckinToken::where('token_hash', MeasureCheckinTokenService::hashToken($token))->value('last_used_at'));
    }

    public function test_employee_cannot_redeem_revoked_measure_checkin_token(): void
    {
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'verification_requirement' => 'QR_CODE',
        ]);
        $token = bin2hex(random_bytes(32));
        MeasureCheckinToken::create([
            'measure_id' => $measure->id,
            'company_id' => $this->company->id,
            'token_hash' => MeasureCheckinTokenService::hashToken($token),
            'created_by_user_id' => User::factory()->create(['company_id' => $this->company->id])->id,
            'valid_from' => now()->subHour(),
            'revoked_at' => now(),
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/employee/measure-checkins/{$token}")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CHECKIN_TOKEN_REVOKED');

        $this->assertDatabaseMissing('measure_participations', [
            'measure_id' => $measure->id,
            'user_id' => $this->employee->id,
        ]);
    }

    public function test_employee_cannot_redeem_expired_measure_checkin_token(): void
    {
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'verification_requirement' => 'QR_CODE',
        ]);
        $token = bin2hex(random_bytes(32));
        MeasureCheckinToken::create([
            'measure_id' => $measure->id,
            'company_id' => $this->company->id,
            'token_hash' => MeasureCheckinTokenService::hashToken($token),
            'created_by_user_id' => User::factory()->create(['company_id' => $this->company->id])->id,
            'valid_from' => now()->subHours(2),
            'valid_until' => now()->subHour(),
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/employee/measure-checkins/{$token}")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CHECKIN_TOKEN_EXPIRED');

        $this->assertDatabaseMissing('measure_participations', [
            'measure_id' => $measure->id,
            'user_id' => $this->employee->id,
        ]);
    }

    public function test_employee_cannot_redeem_not_yet_valid_measure_checkin_token(): void
    {
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'verification_requirement' => 'QR_CODE',
        ]);
        $token = bin2hex(random_bytes(32));
        MeasureCheckinToken::create([
            'measure_id' => $measure->id,
            'company_id' => $this->company->id,
            'token_hash' => MeasureCheckinTokenService::hashToken($token),
            'created_by_user_id' => User::factory()->create(['company_id' => $this->company->id])->id,
            'valid_from' => now()->addHour(),
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/employee/measure-checkins/{$token}")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CHECKIN_TOKEN_NOT_YET_VALID');

        $this->assertDatabaseMissing('measure_participations', [
            'measure_id' => $measure->id,
            'user_id' => $this->employee->id,
        ]);
    }

    public function test_employee_cannot_redeem_qr_checkin_for_other_company_or_team(): void
    {
        $foreignCompany = Company::factory()->create();
        $foreignMeasure = Measure::factory()->create([
            'company_id' => $foreignCompany->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'verification_requirement' => 'QR_CODE',
        ]);
        $foreignToken = bin2hex(random_bytes(32));
        MeasureCheckinToken::create([
            'measure_id' => $foreignMeasure->id,
            'company_id' => $foreignCompany->id,
            'token_hash' => MeasureCheckinTokenService::hashToken($foreignToken),
            'created_by_user_id' => User::factory()->create(['company_id' => $foreignCompany->id])->id,
            'valid_from' => now(),
        ]);

        $team = Team::factory()->create(['company_id' => $this->company->id]);
        $otherTeam = Team::factory()->create(['company_id' => $this->company->id]);
        $this->employee->update(['team_id' => $team->id]);
        $teamMeasure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => $otherTeam->id,
            'status' => 'ACTIVE',
            'verification_requirement' => 'QR_CODE',
        ]);
        $teamToken = bin2hex(random_bytes(32));
        MeasureCheckinToken::create([
            'measure_id' => $teamMeasure->id,
            'company_id' => $this->company->id,
            'token_hash' => MeasureCheckinTokenService::hashToken($teamToken),
            'created_by_user_id' => User::factory()->create(['company_id' => $this->company->id])->id,
            'valid_from' => now(),
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/employee/measure-checkins/{$foreignToken}")
            ->assertStatus(404);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/employee/measure-checkins/{$teamToken}")
            ->assertStatus(404);

        $this->assertSame(0, MeasureParticipation::query()->count());
    }

    public function test_qr_checkin_duplicate_does_not_award_points_twice(): void
    {
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'verification_requirement' => 'QR_CODE',
        ]);
        $token = bin2hex(random_bytes(32));
        MeasureCheckinToken::create([
            'measure_id' => $measure->id,
            'company_id' => $this->company->id,
            'token_hash' => MeasureCheckinTokenService::hashToken($token),
            'created_by_user_id' => User::factory()->create(['company_id' => $this->company->id])->id,
            'valid_from' => now(),
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/employee/measure-checkins/{$token}")
            ->assertStatus(201);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/employee/measure-checkins/{$token}")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'MEASURE_ALREADY_PARTICIPATED');

        $this->assertSame(1, MeasureParticipation::query()
            ->where('measure_id', $measure->id)
            ->where('user_id', $this->employee->id)
            ->count());
        $this->assertSame(1, PointTransaction::query()
            ->where('user_id', $this->employee->id)
            ->where('reason', 'measure_participation')
            ->count());
    }

    public function test_measure_participation_derives_identity_from_authenticated_employee_and_ignores_request_body()
    {
        $team = Team::factory()->create(['company_id' => $this->company->id]);
        $foreignCompany = Company::factory()->create();
        $foreignTeam = Team::factory()->create(['company_id' => $foreignCompany->id]);
        $foreignUser = User::factory()->create([
            'company_id' => $foreignCompany->id,
            'team_id' => $foreignTeam->id,
            'role' => Role::EMPLOYEE,
        ]);
        $this->employee->update(['team_id' => $team->id]);
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'verification_requirement' => 'SELF_REPORT',
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/employee/measures/{$measure->id}/participate", [
                'user_id' => $foreignUser->id,
                'company_id' => $foreignCompany->id,
                'team_id' => $foreignTeam->id,
                'participated_at' => '2025-01-01T00:00:00Z',
                'verification_type' => 'QR_CHECKIN',
                'verified_at' => '2025-01-01T00:00:00Z',
                'verified_by_user_id' => $foreignUser->id,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('measure_participations', [
            'measure_id' => $measure->id,
            'user_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'team_id' => $team->id,
            'verification_type' => 'SELF_REPORTED',
            'verified_by_user_id' => null,
        ]);
        $this->assertDatabaseMissing('measure_participations', [
            'measure_id' => $measure->id,
            'user_id' => $foreignUser->id,
            'company_id' => $foreignCompany->id,
            'team_id' => $foreignTeam->id,
        ]);
        $this->assertDatabaseMissing('measure_participations', [
            'measure_id' => $measure->id,
            'user_id' => $this->employee->id,
            'verification_type' => 'QR_CHECKIN',
            'verified_at' => '2025-01-01 00:00:00',
            'verified_by_user_id' => $foreignUser->id,
        ]);
    }

    public function test_company_user_cannot_use_employee_measure_participation_endpoint()
    {
        $companyUser = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => Role::COMPANY_ADMIN,
        ]);
        $measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($companyUser, 'sanctum')
            ->postJson("/api/employee/measures/{$measure->id}/participate")
            ->assertStatus(403);

        $this->assertDatabaseMissing('measure_participations', [
            'measure_id' => $measure->id,
            'user_id' => $companyUser->id,
        ]);
    }

    public function test_unknown_qr_checkin_token_returns_not_found(): void
    {
        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/employee/measure-checkins/'.bin2hex(random_bytes(32)))
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'CHECKIN_TOKEN_NOT_FOUND');
    }

    public function test_foreign_company_token_masks_lifecycle_state(): void
    {
        $foreignCompany = Company::factory()->create();
        $foreignMeasure = Measure::factory()->create([
            'company_id' => $foreignCompany->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'verification_requirement' => 'QR_CODE',
        ]);
        $creator = User::factory()->create(['company_id' => $foreignCompany->id]);

        $makeToken = function (array $attrs) use ($foreignMeasure, $foreignCompany, $creator): string {
            $raw = bin2hex(random_bytes(32));
            MeasureCheckinToken::create(array_merge([
                'measure_id' => $foreignMeasure->id,
                'company_id' => $foreignCompany->id,
                'token_hash' => MeasureCheckinTokenService::hashToken($raw),
                'created_by_user_id' => $creator->id,
                'valid_from' => now()->subHour(),
            ], $attrs));

            return $raw;
        };

        $activeToken = $makeToken([]);
        $revokedToken = $makeToken(['revoked_at' => now()]);
        $expiredToken = $makeToken(['valid_until' => now()->subMinutes(30)]);
        $notYetValidToken = $makeToken(['valid_from' => now()->addHour()]);

        foreach ([$activeToken, $revokedToken, $expiredToken, $notYetValidToken] as $token) {
            $this->actingAs($this->employee, 'sanctum')
                ->postJson("/api/employee/measure-checkins/{$token}")
                ->assertStatus(404)
                ->assertJsonPath('error.code', 'CHECKIN_TOKEN_NOT_FOUND');
        }

        $this->assertSame(0, MeasureParticipation::query()->count());
    }

    public function test_wrong_team_token_masks_lifecycle_state(): void
    {
        $myTeam = Team::factory()->create(['company_id' => $this->company->id]);
        $otherTeam = Team::factory()->create(['company_id' => $this->company->id]);
        $this->employee->update(['team_id' => $myTeam->id]);

        $teamMeasure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => $otherTeam->id,
            'status' => 'ACTIVE',
            'verification_requirement' => 'QR_CODE',
        ]);
        $creator = User::factory()->create(['company_id' => $this->company->id]);

        $makeToken = function (array $attrs) use ($teamMeasure, $creator): string {
            $raw = bin2hex(random_bytes(32));
            MeasureCheckinToken::create(array_merge([
                'measure_id' => $teamMeasure->id,
                'company_id' => $this->company->id,
                'token_hash' => MeasureCheckinTokenService::hashToken($raw),
                'created_by_user_id' => $creator->id,
                'valid_from' => now()->subHour(),
            ], $attrs));

            return $raw;
        };

        $activeToken = $makeToken([]);
        $revokedToken = $makeToken(['revoked_at' => now()]);
        $expiredToken = $makeToken(['valid_until' => now()->subMinutes(30)]);
        $notYetValidToken = $makeToken(['valid_from' => now()->addHour()]);

        foreach ([$activeToken, $revokedToken, $expiredToken, $notYetValidToken] as $token) {
            $this->actingAs($this->employee, 'sanctum')
                ->postJson("/api/employee/measure-checkins/{$token}")
                ->assertStatus(404)
                ->assertJsonPath('error.code', 'CHECKIN_TOKEN_NOT_FOUND');
        }

        $this->assertSame(0, MeasureParticipation::query()->count());
    }

    public function test_employee_can_upload_medical_pdf()
    {
        Storage::fake('public');

        $response = $this->actingAs($this->employee, 'sanctum')
            ->post('/api/employee/documents', [
                'file' => UploadedFile::fake()->create('report.pdf', 128, 'application/pdf'),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.fileName', 'report.pdf');

        $this->assertDatabaseHas('user_documents', [
            'user_id' => $this->employee->id,
            'file_name' => 'report.pdf',
        ]);
    }

    private function createDailyWellbeingEntry(User $user, string $periodKey, ?Company $company = null): WellbeingEntry
    {
        return WellbeingEntry::factory()->create([
            'user_id' => $user->id,
            'company_id' => ($company ?? $this->company)->id,
            'period_key' => $periodKey,
            'created_at' => Carbon::parse('2026-05-25 08:00:00'),
            'updated_at' => Carbon::parse('2026-05-25 08:00:00'),
        ]);
    }

    private function createPreviousWorkdayEntries(User $user, string $periodKey, int $count): void
    {
        $date = Carbon::parse($periodKey)->startOfDay();

        for ($i = 0; $i < $count; $i++) {
            do {
                $date->subDay();
            } while ($date->isWeekend());

            $this->createDailyWellbeingEntry($user, $date->toDateString());
        }
    }
}
