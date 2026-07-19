<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PointTransaction;
use App\Models\SystemExercise;
use App\Models\SystemExerciseTag;
use App\Models\SystemMeasureTemplate;
use App\Models\SystemMeasureTemplateExercise;
use App\Models\User;
use App\Models\UserSystemMeasure;
use App\Models\UserSystemMeasureExercise;
use App\Models\UserSystemMeasureExerciseCompletion;
use Database\Seeders\SystemExerciseSeeder;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class SystemMeasureDataModelTest extends TestCase
{

    // ── SystemExercise ──

    public function test_system_exercise_can_be_created_with_factory_defaults(): void
    {
        $exercise = SystemExercise::factory()->create();

        $this->assertDatabaseHas('system_exercises', [
            'id' => $exercise->id,
            'exercise_type' => 'MOBILITY',
            'difficulty' => 'BEGINNER',
            'status' => 'ACTIVE',
            'requires_feedback' => true,
        ]);
    }

    public function test_system_exercise_generates_slug_from_title(): void
    {
        $exercise = SystemExercise::create([
            'title' => 'Nacken Stretch Übung',
        ]);

        $this->assertSame('nacken-stretch-ubung', $exercise->slug);
    }

    public function test_system_exercise_slug_must_be_unique(): void
    {
        SystemExercise::factory()->create(['slug' => 'test-exercise']);

        $this->expectException(QueryException::class);
        SystemExercise::factory()->create(['slug' => 'test-exercise']);
    }

    public function test_system_exercise_can_have_many_tags(): void
    {
        $exercise = SystemExercise::factory()->create();
        $tags = SystemExerciseTag::factory()->count(3)->create();

        $exercise->tags()->attach($tags->pluck('id'));

        $this->assertCount(3, $exercise->fresh()->tags);
    }

    public function test_system_exercise_tag_can_belong_to_many_exercises(): void
    {
        $tag = SystemExerciseTag::factory()->create();
        $exercises = SystemExercise::factory()->count(2)->create();

        $tag->exercises()->attach($exercises->pluck('id'));

        $this->assertCount(2, $tag->fresh()->exercises);
    }

    public function test_system_exercise_tag_category_key_must_be_unique(): void
    {
        SystemExerciseTag::factory()->create(['category' => 'BODY_REGION', 'key' => 'NECK']);

        $this->expectException(QueryException::class);
        SystemExerciseTag::factory()->create(['category' => 'BODY_REGION', 'key' => 'NECK']);
    }

    // ── SystemMeasureTemplate ──

    public function test_system_measure_template_can_be_created_with_factory_defaults(): void
    {
        $template = SystemMeasureTemplate::factory()->create();

        $this->assertDatabaseHas('system_measure_templates', [
            'id' => $template->id,
            'difficulty' => 'BEGINNER',
            'status' => 'ACTIVE',
            'streak_enabled' => true,
            'requires_feedback' => true,
        ]);
    }

    public function test_template_can_have_exercises_with_positions(): void
    {
        $template = SystemMeasureTemplate::factory()->create();
        $exercises = SystemExercise::factory()->count(3)->create();

        foreach ($exercises as $i => $exercise) {
            SystemMeasureTemplateExercise::create([
                'system_measure_template_id' => $template->id,
                'system_exercise_id' => $exercise->id,
                'position' => $i + 1,
            ]);
        }

        $loaded = $template->fresh()->templateExercises;

        $this->assertCount(3, $loaded);
        $this->assertSame(1, $loaded->first()->position);
        $this->assertSame(3, $loaded->last()->position);
    }

    public function test_template_exercise_position_is_unique_per_template(): void
    {
        $template = SystemMeasureTemplate::factory()->create();
        $exercises = SystemExercise::factory()->count(2)->create();

        SystemMeasureTemplateExercise::create([
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => $exercises[0]->id,
            'position' => 1,
        ]);

        $this->expectException(QueryException::class);
        SystemMeasureTemplateExercise::create([
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => $exercises[1]->id,
            'position' => 1,
        ]);
    }

    public function test_different_templates_can_use_same_exercise(): void
    {
        $exercise = SystemExercise::factory()->create();
        $templates = SystemMeasureTemplate::factory()->count(2)->create();

        foreach ($templates as $template) {
            SystemMeasureTemplateExercise::create([
                'system_measure_template_id' => $template->id,
                'system_exercise_id' => $exercise->id,
                'position' => 1,
            ]);
        }

        $this->assertCount(2, $exercise->fresh()->templateExercises);
    }

    public function test_template_exercise_overrides_are_stored(): void
    {
        $template = SystemMeasureTemplate::factory()->create();
        $exercise = SystemExercise::factory()->create(['instructions' => 'Original instructions']);

        $te = SystemMeasureTemplateExercise::create([
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => $exercise->id,
            'position' => 1,
            'custom_title' => 'Custom Title',
            'custom_instructions' => 'Custom instructions for this template',
            'custom_duration_minutes' => 15,
        ]);

        $this->assertSame('Custom Title', $te->fresh()->custom_title);
        $this->assertSame('Custom instructions for this template', $te->fresh()->custom_instructions);
        $this->assertSame(15, $te->fresh()->custom_duration_minutes);
    }

    // ── Snapshot semantics ──

    public function test_user_system_measure_snapshots_template_fields(): void
    {
        $userMeasure = UserSystemMeasure::factory()->fromTemplate()->create();

        $template = $userMeasure->sourceTemplate;
        $this->assertNotNull($template);
        $this->assertSame($template->title, $userMeasure->title);
        $this->assertSame($template->description, $userMeasure->description);
    }

    public function test_user_system_measure_exercises_snapshot_exercise_data(): void
    {
        $userMeasure = UserSystemMeasure::factory()->fromTemplate()->create();

        $snapshotExercises = $userMeasure->exercises;
        $this->assertCount(3, $snapshotExercises);

        foreach ($snapshotExercises as $snapshot) {
            $source = $snapshot->sourceExercise;
            $this->assertNotNull($source);
            $this->assertSame($source->title, $snapshot->title);
            $this->assertSame($source->exercise_type, $snapshot->exercise_type);
            $this->assertSame($source->difficulty, $snapshot->difficulty);
        }
    }

    public function test_editing_source_exercise_does_not_change_user_snapshot(): void
    {
        $userMeasure = UserSystemMeasure::factory()->fromTemplate()->create();
        $snapshot = $userMeasure->exercises->first();
        $originalTitle = $snapshot->title;
        $sourceExercise = $snapshot->sourceExercise;

        $sourceExercise->update(['title' => 'Completely Changed Title']);

        $snapshot->refresh();
        $this->assertSame($originalTitle, $snapshot->title);
        $this->assertNotSame('Completely Changed Title', $snapshot->title);
    }

    public function test_deleting_source_exercise_preserves_user_snapshot(): void
    {
        $userMeasure = UserSystemMeasure::factory()->fromTemplate()->create();
        $snapshot = $userMeasure->exercises->first();
        $originalTitle = $snapshot->title;
        $sourceId = $snapshot->source_system_exercise_id;

        SystemExercise::destroy($sourceId);

        $snapshot->refresh();
        $this->assertSame($originalTitle, $snapshot->title);
        $this->assertNull($snapshot->source_system_exercise_id);
    }

    public function test_deleting_template_preserves_user_assignment(): void
    {
        $userMeasure = UserSystemMeasure::factory()->fromTemplate()->create();
        $originalTitle = $userMeasure->title;
        $templateId = $userMeasure->source_system_measure_template_id;

        SystemMeasureTemplate::destroy($templateId);

        $userMeasure->refresh();
        $this->assertSame($originalTitle, $userMeasure->title);
        $this->assertNull($userMeasure->source_system_measure_template_id);
        $this->assertCount(3, $userMeasure->exercises);
    }

    // ── UserSystemMeasure ──

    public function test_user_system_measure_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $userMeasure = UserSystemMeasure::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertSame($user->id, $userMeasure->user->id);
    }

    public function test_company_is_derived_through_user(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $userMeasure = UserSystemMeasure::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertSame($company->id, $userMeasure->user->company->id);
    }

    public function test_user_system_measure_defaults_to_assigned_status(): void
    {
        $userMeasure = UserSystemMeasure::factory()->create();

        $this->assertSame('ASSIGNED', $userMeasure->status);
        $this->assertNotNull($userMeasure->assigned_at);
    }

    public function test_user_can_have_multiple_system_measures(): void
    {
        $user = User::factory()->create();

        UserSystemMeasure::factory()->count(3)->create([
            'user_id' => $user->id,
        ]);

        $this->assertCount(3, $user->fresh()->userSystemMeasures);
    }

    // ── Cascade / FK tests ──

    public function test_deleting_user_cascades_to_user_system_measures(): void
    {
        $user = User::factory()->create();
        UserSystemMeasure::factory()->create([
            'user_id' => $user->id,
        ]);

        $user->delete();

        $this->assertDatabaseCount('user_system_measures', 0);
    }

    public function test_deleting_user_system_measure_cascades_to_exercises(): void
    {
        $userMeasure = UserSystemMeasure::factory()->fromTemplate()->create();
        $this->assertDatabaseCount('user_system_measure_exercises', 3);

        $userMeasure->delete();

        $this->assertDatabaseCount('user_system_measure_exercises', 0);
    }

    public function test_deleting_user_system_measure_exercise_cascades_to_completions(): void
    {
        $userMeasure = UserSystemMeasure::factory()->create();
        $exercise = UserSystemMeasureExercise::factory()->create([
            'user_system_measure_id' => $userMeasure->id,
        ]);
        UserSystemMeasureExerciseCompletion::factory()->create([
            'user_system_measure_exercise_id' => $exercise->id,
        ]);

        $exercise->delete();

        $this->assertDatabaseCount('user_system_measure_exercise_completions', 0);
    }

    public function test_deleting_system_exercise_cascades_to_template_exercises(): void
    {
        $template = SystemMeasureTemplate::factory()->withExercises(2)->create();
        $this->assertDatabaseCount('system_measure_template_exercises', 2);

        $firstExerciseId = $template->templateExercises->first()->system_exercise_id;
        SystemExercise::destroy($firstExerciseId);

        $this->assertDatabaseCount('system_measure_template_exercises', 1);
    }

    public function test_deleting_template_cascades_to_template_exercises(): void
    {
        $template = SystemMeasureTemplate::factory()->withExercises(3)->create();
        $this->assertDatabaseCount('system_measure_template_exercises', 3);

        $template->delete();

        $this->assertDatabaseCount('system_measure_template_exercises', 0);
    }

    // ── Completion ──

    public function test_completion_belongs_to_assigned_exercise(): void
    {
        $userMeasure = UserSystemMeasure::factory()->create();
        $exercise = UserSystemMeasureExercise::factory()->create([
            'user_system_measure_id' => $userMeasure->id,
        ]);

        $completion = UserSystemMeasureExerciseCompletion::factory()->create([
            'user_system_measure_exercise_id' => $exercise->id,
        ]);

        $this->assertSame($exercise->id, $completion->exercise->id);
    }

    public function test_completion_user_derived_through_exercise_chain(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $userMeasure = UserSystemMeasure::factory()->create(['user_id' => $user->id]);
        $exercise = UserSystemMeasureExercise::factory()->create([
            'user_system_measure_id' => $userMeasure->id,
        ]);

        $completion = UserSystemMeasureExerciseCompletion::factory()->create([
            'user_system_measure_exercise_id' => $exercise->id,
        ]);

        // Derive user through: completion -> exercise -> userSystemMeasure -> user
        $derivedUser = $completion->exercise->userSystemMeasure->user;
        $this->assertSame($user->id, $derivedUser->id);

        // Derive company through: ... -> user -> company
        $derivedCompany = $derivedUser->company;
        $this->assertSame($company->id, $derivedCompany->id);
    }

    public function test_exercise_completion_can_store_feedback_without_points(): void
    {
        $exercise = UserSystemMeasureExercise::factory()->create();

        $completion = UserSystemMeasureExerciseCompletion::create([
            'user_system_measure_exercise_id' => $exercise->id,
            'completed_at' => now(),
            'feedback_text' => 'Felt great after this exercise.',
            'effort_rating' => 3,
            'difficulty_rating' => 2,
            'pain_before_rating' => 4,
            'pain_after_rating' => 2,
        ]);

        $this->assertDatabaseHas('user_system_measure_exercise_completions', [
            'id' => $completion->id,
            'feedback_text' => 'Felt great after this exercise.',
            'effort_rating' => 3,
            'pain_before_rating' => 4,
            'pain_after_rating' => 2,
            'points_awarded' => null,
            'points_transaction_id' => null,
        ]);
    }

    public function test_exercise_can_have_multiple_completions(): void
    {
        $exercise = UserSystemMeasureExercise::factory()->create();

        UserSystemMeasureExerciseCompletion::factory()->count(3)->create([
            'user_system_measure_exercise_id' => $exercise->id,
        ]);

        $this->assertCount(3, $exercise->fresh()->completions);
    }

    public function test_completion_can_reference_points_transaction(): void
    {
        $userMeasure = UserSystemMeasure::factory()->create();
        $exercise = UserSystemMeasureExercise::factory()->create([
            'user_system_measure_id' => $userMeasure->id,
        ]);
        $transaction = PointTransaction::create([
            'user_id' => $userMeasure->user_id,
            'points' => 10,
            'reason' => 'system_exercise_completion',
        ]);

        $completion = UserSystemMeasureExerciseCompletion::create([
            'user_system_measure_exercise_id' => $exercise->id,
            'completed_at' => now(),
            'points_awarded' => 10,
            'points_transaction_id' => $transaction->id,
        ]);

        $this->assertSame($transaction->id, $completion->fresh()->pointsTransaction->id);
    }

    public function test_default_completion_factory_creates_consistent_data(): void
    {
        $completion = UserSystemMeasureExerciseCompletion::factory()->create();

        $exercise = $completion->exercise;
        $this->assertNotNull($exercise);

        $userMeasure = $exercise->userSystemMeasure;
        $this->assertNotNull($userMeasure);

        $user = $userMeasure->user;
        $this->assertNotNull($user);
        $this->assertNotNull($user->company);
    }

    // ── Seeder ──

    public function test_system_exercise_seeder_creates_expected_data(): void
    {
        $this->seed(SystemExerciseSeeder::class);

        $this->assertGreaterThanOrEqual(9, SystemExercise::count());
        $this->assertGreaterThanOrEqual(25, SystemExerciseTag::count());
        $this->assertGreaterThanOrEqual(5, SystemMeasureTemplate::count());

        $template = SystemMeasureTemplate::where('slug', 'ruecken-mobilitaet-bueroarbeit')->first();
        $this->assertNotNull($template);
        $this->assertGreaterThanOrEqual(2, $template->templateExercises->count());

        $exercise = SystemExercise::where('slug', 'seitliche-nackendehnung')->first();
        $this->assertNotNull($exercise);
        $this->assertGreaterThanOrEqual(1, $exercise->tags->count());
    }

    public function test_system_exercise_seeder_is_idempotent(): void
    {
        $this->seed(SystemExerciseSeeder::class);
        $countAfterFirst = SystemExercise::count();

        $this->seed(SystemExerciseSeeder::class);
        $countAfterSecond = SystemExercise::count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
    }
}
