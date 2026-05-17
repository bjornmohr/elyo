<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\WellbeingEntry;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveyAnswer;
use App\Models\Measure;
use App\Models\Team;
use App\Enums\Role;
use App\Enums\SurveyStatus;
use App\Enums\QuestionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
            'mood' => 8,
            'stress' => 3,
            'energy' => 7,
        ]);
    }

    public function test_employee_can_submit_checkin_only_once_per_day()
    {
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
                    ]
                ]
            ]);

        $response->assertStatus(200)
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('survey_responses', [
            'user_id' => $this->employee->id,
            'survey_id' => $survey->id,
        ]);
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
}
