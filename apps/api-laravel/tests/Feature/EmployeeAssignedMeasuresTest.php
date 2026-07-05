<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\SystemExercise;
use App\Models\SystemMeasureTemplate;
use App\Models\SystemMeasureTemplateExercise;
use App\Models\User;
use App\Models\UserSystemMeasure;
use App\Models\UserSystemMeasureExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAssignedMeasuresTest extends TestCase
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

    private function createTemplateWithExercise(): array
    {
        $template = SystemMeasureTemplate::create([
            'slug' => 'nacken-mobilitaet-test',
            'title' => 'Nacken-Mobilität',
            'description' => 'Testprogramm',
            'category' => 'MOBILITY',
            'estimated_duration_minutes' => 10,
            'target_signal' => 'neck_pain',
            'assignment_reason_template' => 'aus Check-in „Nackenschmerzen“',
            'effect_metric' => 'pain',
            'effect_metric_unit' => 'nrs_0_10',
            'location_tags' => ['office', 'plant'],
            'posture_tags' => ['standing'],
            'requires_floor' => false,
        ]);

        $exercise = SystemExercise::create([
            'slug' => 'schulterkreisen-test',
            'title' => 'Schulterkreisen',
            'exercise_type' => 'MOBILITY',
            'default_sets' => 2,
            'default_repetitions' => 15,
            'main_pictogram_path' => 'pictograms/nacken-mobilitaet/schulterkreisen/main.svg',
            'main_pictogram_alt' => 'Strichfigur mit Rotationspfeilen.',
            'steps' => [
                ['text' => 'Aufrecht hinstellen.', 'pictogram_path' => 'pictograms/nacken-mobilitaet/schulterkreisen/step-1.svg', 'alt' => 'Stehende Figur.'],
                ['text' => 'Schultern kreisen.', 'pictogram_path' => null, 'alt' => null],
            ],
            'posture_tags' => ['standing'],
            'default_effort' => 2,
        ]);

        SystemMeasureTemplateExercise::create([
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => $exercise->id,
            'position' => 1,
        ]);

        return [$template, $exercise];
    }

    private function assignMeasure(User $user, SystemMeasureTemplate $template, SystemExercise $exercise): UserSystemMeasure
    {
        $measure = UserSystemMeasure::create([
            'user_id' => $user->id,
            'source_system_measure_template_id' => $template->id,
            'title' => $template->title,
            'description' => $template->description,
            'assignment_reason' => $template->assignment_reason_template,
            'recommendation_context' => ['demo' => [
                'streakDays' => 5, 'weeklyTarget' => 4, 'weeklyDone' => 3,
                'lastEffect' => ['before' => 6, 'after' => 3],
                'lastEffort' => 2, 'lastPoints' => 5,
            ]],
            'status' => UserSystemMeasure::STATUS_ACTIVE,
        ]);

        UserSystemMeasureExercise::create([
            'user_system_measure_id' => $measure->id,
            'source_system_exercise_id' => $exercise->id,
            'position' => 1,
            'title' => $exercise->title,
            'exercise_type' => $exercise->exercise_type,
            'sets' => $exercise->default_sets,
            'repetitions' => $exercise->default_repetitions,
        ]);

        return $measure;
    }

    public function test_index_returns_only_own_assigned_measures_with_3a_fields(): void
    {
        [$template, $exercise] = $this->createTemplateWithExercise();
        $this->assignMeasure($this->employee, $template, $exercise);

        $otherEmployee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => Role::EMPLOYEE,
        ]);
        $this->assignMeasure($otherEmployee, $template, $exercise);

        $response = $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/measures');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Nacken-Mobilität')
            ->assertJsonPath('data.0.category', 'MOBILITY')
            ->assertJsonPath('data.0.assignmentReason', 'aus Check-in „Nackenschmerzen“')
            ->assertJsonPath('data.0.exerciseCount', 1)
            ->assertJsonPath('data.0.estMinutes', 10)
            ->assertJsonPath('data.0.streakDays', 5)
            ->assertJsonPath('data.0.weeklyDone', 3)
            ->assertJsonPath('data.0.weeklyTarget', 4)
            ->assertJsonPath('data.0.effect.metric', 'pain')
            ->assertJsonPath('data.0.effect.before', 6)
            ->assertJsonPath('data.0.effect.after', 3)
            ->assertJsonPath('data.0.effect.direction', 'down')
            ->assertJsonPath('data.0.locationTags', ['office', 'plant'])
            ->assertJsonPath('data.0.requiresFloor', false);
    }

    public function test_show_resolves_pictograms_and_steps_from_source_exercise(): void
    {
        [$template, $exercise] = $this->createTemplateWithExercise();
        $measure = $this->assignMeasure($this->employee, $template, $exercise);

        $response = $this->actingAs($this->employee, 'sanctum')
            ->getJson("/api/employee/measures/{$measure->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $measure->id)
            ->assertJsonPath('data.lastSession.effort', 2)
            ->assertJsonPath('data.lastSession.points', 5)
            ->assertJsonPath('data.exercises.0.title', 'Schulterkreisen')
            ->assertJsonPath('data.exercises.0.sets', 2)
            ->assertJsonPath('data.exercises.0.repetitions', 15)
            ->assertJsonPath('data.exercises.0.mainPictogramPath', 'pictograms/nacken-mobilitaet/schulterkreisen/main.svg')
            ->assertJsonPath('data.exercises.0.steps.0.text', 'Aufrecht hinstellen.')
            ->assertJsonPath('data.exercises.0.steps.0.pictogramPath', 'pictograms/nacken-mobilitaet/schulterkreisen/step-1.svg')
            ->assertJsonPath('data.exercises.0.defaultEffort', 2)
            ->assertJsonPath('data.exercises.0.postureTags', ['standing'])
            // Exercise has no own location tags — falls back to the template.
            ->assertJsonPath('data.exercises.0.locationTags', ['office', 'plant']);
    }

    public function test_show_returns_404_for_foreign_measure(): void
    {
        [$template, $exercise] = $this->createTemplateWithExercise();
        $otherEmployee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => Role::EMPLOYEE,
        ]);
        $foreignMeasure = $this->assignMeasure($otherEmployee, $template, $exercise);

        $this->actingAs($this->employee, 'sanctum')
            ->getJson("/api/employee/measures/{$foreignMeasure->id}")
            ->assertStatus(404);
    }

    public function test_non_employee_role_gets_403(): void
    {
        $admin = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => Role::COMPANY_ADMIN,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/employee/measures')
            ->assertStatus(403);
    }

    public function test_cancelled_measures_are_not_listed(): void
    {
        [$template, $exercise] = $this->createTemplateWithExercise();
        $measure = $this->assignMeasure($this->employee, $template, $exercise);
        $measure->update(['status' => UserSystemMeasure::STATUS_CANCELLED]);

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/measures')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
