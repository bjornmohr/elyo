<?php

namespace Tests\Support;

use App\Enums\PartnerVerificationStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Health\LabMarker;
use App\Models\Health\LabMarkerReading;
use App\Models\Health\WellbeingEntry;
use App\Models\InviteToken;
use App\Models\Measure;
use App\Models\Partner;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SystemExercise;
use App\Models\SystemMeasureTemplate;
use App\Models\SystemMeasureTemplateExercise;
use App\Models\Team;
use App\Models\User;
use App\Services\Privacy\MappingCryptography;
use App\Services\Privacy\MappingServiceContract;
use App\Services\Privacy\PurposeCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Deterministic synthetic fixtures for the standalone privacy suite.
 *
 * DemoDataSeeder is intentionally not used: its product-demo records and future
 * evolution must not determine privacy-regression coverage.
 */
class PrivacySeeder extends Seeder
{
    public Company $company;

    public Team $team;

    public Team $mutableTeam;

    /** @var array<string, User> */
    public array $users = [];

    public User $employee;

    public User $otherEmployee;

    public Survey $survey;

    public Survey $draftSurvey;

    public Survey $deletableSurvey;

    public Measure $measure;

    public SystemExercise $systemExercise;

    public SystemExercise $archivableSystemExercise;

    public SystemExercise $attachableSystemExercise;

    public SystemMeasureTemplate $systemMeasureTemplate;

    public SystemMeasureTemplate $archivableSystemMeasureTemplate;

    public SystemMeasureTemplateExercise $templateExercise;

    public InviteToken $revocableInvite;

    public Partner $partner;

    public string $employeeSubjectId;

    public string $otherEmployeeSubjectId;

    public WellbeingEntry $ownWellbeing;

    public WellbeingEntry $foreignWellbeing;

    public LabMarkerReading $ownLabReading;

    public LabMarkerReading $foreignLabReading;

    public function run(): void
    {
        $this->configureSyntheticMappingKeys();

        $this->company = Company::factory()->create([
            'name' => 'Privacy Test Company',
            'slug' => 'privacy-test-company',
            'status' => 'active',
            'anonymity_threshold' => 5,
            'team_layer_enabled' => true,
        ]);

        foreach ([
            Role::COMPANY_OWNER,
            Role::COMPANY_ADMIN,
            Role::COMPANY_MANAGER,
            Role::ELYO_ADMIN,
            Role::ELYO_SUPPORT,
            Role::PARTNER,
        ] as $role) {
            $this->users[$role->value] = User::factory()->create([
                'name' => 'Synthetic '.$role->value,
                'email' => strtolower($role->value).'@privacy.invalid',
                'company_id' => $this->company->id,
                'role' => $role,
                'status' => 'active',
            ]);
        }

        $this->team = Team::factory()->create([
            'name' => 'Synthetic Team',
            'company_id' => $this->company->id,
            'manager_id' => $this->users[Role::COMPANY_MANAGER->value]->id,
        ]);
        $this->mutableTeam = Team::factory()->create([
            'name' => 'Synthetic Mutable Team',
            'company_id' => $this->company->id,
            'manager_id' => null,
        ]);

        $this->employee = User::factory()->create([
            'name' => 'Synthetic Employee A',
            'email' => 'employee-a@privacy.invalid',
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'role' => Role::EMPLOYEE,
            'status' => 'active',
        ]);
        $this->otherEmployee = User::factory()->create([
            'name' => 'Synthetic Employee B',
            'email' => 'employee-b@privacy.invalid',
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'role' => Role::EMPLOYEE,
            'status' => 'active',
        ]);

        $this->survey = Survey::factory()->create([
            'title' => 'Synthetic privacy survey',
            'description' => 'Synthetic aggregate-only survey fixture.',
            'company_id' => $this->company->id,
            'status' => 'ACTIVE',
            'is_anonymous' => true,
        ]);
        $surveyQuestion = SurveyQuestion::factory()->create([
            'survey_id' => $this->survey->id,
            'text' => 'Synthetic scale question',
            'type' => 'SCALE',
            'order' => 0,
            'is_required' => true,
        ]);
        $this->draftSurvey = Survey::factory()->create([
            'title' => 'Synthetic editable privacy survey',
            'description' => 'Synthetic route-sweep fixture.',
            'company_id' => $this->company->id,
            'created_by' => $this->users[Role::COMPANY_ADMIN->value]->id,
            'status' => 'DRAFT',
            'is_anonymous' => true,
        ]);
        SurveyQuestion::factory()->create([
            'survey_id' => $this->draftSurvey->id,
            'text' => 'Synthetic editable scale question',
            'type' => 'SCALE',
            'order' => 0,
            'is_required' => true,
        ]);
        $this->deletableSurvey = Survey::factory()->create([
            'title' => 'Synthetic deletable privacy survey',
            'description' => 'Synthetic route-sweep fixture.',
            'company_id' => $this->company->id,
            'created_by' => $this->users[Role::COMPANY_ADMIN->value]->id,
            'status' => 'DRAFT',
            'is_anonymous' => true,
        ]);

        for ($index = 1; $index <= 10; $index++) {
            $respondent = User::factory()->create([
                'name' => "Synthetic Survey Respondent {$index}",
                'email' => "survey-respondent-{$index}@privacy.invalid",
                'company_id' => $this->company->id,
                'team_id' => $this->team->id,
                'role' => Role::EMPLOYEE,
                'status' => 'active',
            ]);
            $surveyResponse = SurveyResponse::factory()->create([
                'survey_id' => $this->survey->id,
                'company_id' => $this->company->id,
                'user_id' => $respondent->id,
                'submitted_at' => now(),
            ]);
            SurveyAnswer::factory()->create([
                'response_id' => $surveyResponse->id,
                'question_id' => $surveyQuestion->id,
                'scale_value' => 3,
            ]);
        }

        $this->measure = Measure::factory()->create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'title' => 'Synthetic privacy measure',
            'category' => 'workshop',
            'description' => 'Synthetic route-sweep fixture.',
            'status' => 'ACTIVE',
            'created_by' => $this->users[Role::COMPANY_ADMIN->value]->id,
            'verification_requirement' => Measure::VERIFICATION_REQUIREMENT_QR_CODE,
        ]);

        $this->systemExercise = SystemExercise::factory()->create([
            'slug' => 'synthetic-privacy-exercise',
            'title' => 'Synthetic Privacy Exercise',
            'short_description' => 'Synthetic route-sweep fixture.',
            'description' => 'Synthetic route-sweep fixture.',
            'instructions' => 'Synthetic instructions.',
            'default_duration_minutes' => 10,
            'status' => SystemExercise::STATUS_ACTIVE,
        ]);
        $this->archivableSystemExercise = SystemExercise::factory()->create([
            'slug' => 'synthetic-privacy-archive-exercise',
            'title' => 'Synthetic Privacy Archive Exercise',
            'status' => SystemExercise::STATUS_ACTIVE,
        ]);
        $this->attachableSystemExercise = SystemExercise::factory()->create([
            'slug' => 'synthetic-privacy-attachable-exercise',
            'title' => 'Synthetic Privacy Attachable Exercise',
            'status' => SystemExercise::STATUS_ACTIVE,
        ]);
        $this->systemMeasureTemplate = SystemMeasureTemplate::factory()->create([
            'slug' => 'synthetic-privacy-template',
            'title' => 'Synthetic Privacy Template',
            'short_description' => 'Synthetic route-sweep fixture.',
            'description' => 'Synthetic route-sweep fixture.',
            'estimated_duration_minutes' => 30,
            'default_points' => 10,
            'status' => SystemMeasureTemplate::STATUS_ACTIVE,
        ]);
        $this->archivableSystemMeasureTemplate = SystemMeasureTemplate::factory()->create([
            'slug' => 'synthetic-privacy-archive-template',
            'title' => 'Synthetic Privacy Archive Template',
            'status' => SystemMeasureTemplate::STATUS_ACTIVE,
        ]);
        $this->templateExercise = SystemMeasureTemplateExercise::factory()->create([
            'system_measure_template_id' => $this->systemMeasureTemplate->id,
            'system_exercise_id' => $this->systemExercise->id,
            'position' => 1,
            'is_required' => true,
        ]);
        $this->revocableInvite = InviteToken::create([
            'company_id' => $this->company->id,
            'team_id' => $this->team->id,
            'email' => 'revocable-invite@privacy.invalid',
            'role' => Role::EMPLOYEE,
            'token_hash' => hash('sha256', 'privacy-suite-revocable-invite'),
            'status' => 'pending',
            'invited_by_user_id' => $this->users[Role::COMPANY_ADMIN->value]->id,
            'expires_at' => now()->addDay(),
        ]);
        $this->partner = Partner::create([
            'email' => 'review-partner@privacy.invalid',
            'password_hash' => Hash::make('synthetic-partner-password'),
            'name' => 'Synthetic Review Partner',
            'type' => 'coach',
            'description' => 'Synthetic route-sweep fixture.',
            'verification_status' => PartnerVerificationStatus::PENDING_REVIEW,
        ]);

        $mappingService = app(MappingServiceContract::class);
        $this->employeeSubjectId = $mappingService->provisionOwnSubject(
            $this->employee->id,
            PurposeCode::PROVISIONING,
        );
        $this->otherEmployeeSubjectId = $mappingService->provisionOwnSubject(
            $this->otherEmployee->id,
            PurposeCode::PROVISIONING,
        );

        $this->ownWellbeing = WellbeingEntry::factory()->create([
            'health_subject_id' => $this->employeeSubjectId,
            'mood' => 3,
            'stress' => 2,
            'energy' => 4,
            'score' => 3.7,
            'period_key' => '2026-07-20',
        ]);
        $this->foreignWellbeing = WellbeingEntry::factory()->create([
            'health_subject_id' => $this->otherEmployeeSubjectId,
            'mood' => 5,
            'stress' => 1,
            'energy' => 5,
            'score' => 4.7,
            'period_key' => '2026-07-21',
        ]);

        LabMarker::factory()->create([
            'marker_key' => 'ferritin',
            'name' => 'Synthetic Ferritin',
            'unit' => 'ng/ml',
            'low' => '30.0000',
            'high' => '300.0000',
            'marker_group' => 'sonstige',
            'active' => true,
        ]);
        $this->ownLabReading = LabMarkerReading::factory()->create([
            'health_subject_id' => $this->employeeSubjectId,
            'marker_key' => 'ferritin',
            'value' => '42.0000',
            'measured_at' => '2026-07-20',
            'source' => 'manual',
        ]);
        $this->foreignLabReading = LabMarkerReading::factory()->create([
            'health_subject_id' => $this->otherEmployeeSubjectId,
            'marker_key' => 'ferritin',
            'value' => '99.0000',
            'measured_at' => '2026-07-21',
            'source' => 'manual',
        ]);
    }

    /**
     * @return list<string>
     */
    public function healthSubjectIds(): array
    {
        return [$this->employeeSubjectId, $this->otherEmployeeSubjectId];
    }

    private function configureSyntheticMappingKeys(): void
    {
        config()->set('privacy.mapping.encryption_key', 'base64:a2tra2tra2tra2tra2tra2tra2tra2tra2tra2tra2s=');
        config()->set('privacy.mapping.hmac_key', 'privacy-suite-hmac-key');
        config()->set('privacy.mapping.subject_derivation_key', 'privacy-suite-subject-key');
        config()->set('app.key', 'base64:bW1tbW1tbW1tbW1tbW1tbW1tbW1tbW1tbW1tbW1tbW0=');
        app()->forgetInstance(MappingCryptography::class);
    }
}
