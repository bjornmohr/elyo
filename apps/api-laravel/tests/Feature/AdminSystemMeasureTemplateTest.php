<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\SystemExercise;
use App\Models\SystemMeasureTemplate;
use App\Models\SystemMeasureTemplateExercise;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminSystemMeasureTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected User $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platformAdmin = User::factory()->platformAdmin()->create([
            'role' => Role::ELYO_ADMIN,
        ]);
    }

    private function createTemplateWithExercises(int $count = 3): SystemMeasureTemplate
    {
        return SystemMeasureTemplate::factory()->withExercises($count)->create();
    }

    // ── Authorization ──

    public function test_platform_admin_can_list_templates(): void
    {
        SystemMeasureTemplate::factory()->count(2)->create();

        $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-measure-templates')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_elyo_support_can_list_templates(): void
    {
        $support = User::factory()->platformAdmin()->create(['role' => Role::ELYO_SUPPORT]);
        SystemMeasureTemplate::factory()->create();

        $this->actingAs($support)
            ->getJson('/api/admin/system-measure-templates')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_unauthenticated_user_cannot_list_templates(): void
    {
        $this->getJson('/api/admin/system-measure-templates')->assertUnauthorized();
    }

    public function test_employee_cannot_list_templates(): void
    {
        $employee = User::factory()->create(['role' => Role::EMPLOYEE]);

        $this->actingAs($employee)->getJson('/api/admin/system-measure-templates')->assertForbidden();
    }

    public function test_company_admin_cannot_list_templates(): void
    {
        $companyAdmin = User::factory()->create(['role' => Role::COMPANY_ADMIN]);

        $this->actingAs($companyAdmin)->getJson('/api/admin/system-measure-templates')->assertForbidden();
    }

    public function test_company_manager_cannot_create_template(): void
    {
        $manager = User::factory()->create(['role' => Role::COMPANY_MANAGER]);

        $this->actingAs($manager)
            ->postJson('/api/admin/system-measure-templates', ['title' => 'Forbidden'])
            ->assertForbidden();
    }

    public function test_employee_cannot_manage_template_exercises(): void
    {
        $employee = User::factory()->create(['role' => Role::EMPLOYEE]);
        $template = $this->createTemplateWithExercises(1);
        $exercise = SystemExercise::factory()->create();

        $this->actingAs($employee)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/exercises", [
                'systemExerciseId' => $exercise->id,
            ])
            ->assertForbidden();
    }

    // ── List ──

    public function test_list_returns_expected_fields_and_pagination_shape(): void
    {
        $this->createTemplateWithExercises(2);

        $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-measure-templates')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'slug', 'title', 'shortDescription', 'category', 'difficulty',
                        'estimatedDurationMinutes', 'status', 'isFeatured', 'exerciseCount',
                        'createdAt', 'updatedAt'],
                ],
                'links',
                'meta' => ['current_page', 'total'],
            ])
            ->assertJsonPath('data.0.exerciseCount', 2);
    }

    public function test_list_sorts_newest_first(): void
    {
        $older = SystemMeasureTemplate::factory()->create(['created_at' => now()->subDay()]);
        $newer = SystemMeasureTemplate::factory()->create(['created_at' => now()]);

        $response = $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-measure-templates')
            ->assertOk();

        $this->assertSame($newer->id, $response->json('data.0.id'));
        $this->assertSame($older->id, $response->json('data.1.id'));
    }

    public function test_list_filters_by_search_across_text_fields_and_slug(): void
    {
        SystemMeasureTemplate::factory()->create([
            'title' => 'Stress Reset Plan',
            'slug' => 'stress-reset-plan',
            'short_description' => 'Kurz',
            'description' => 'Lang',
        ]);
        SystemMeasureTemplate::factory()->create([
            'title' => 'Other',
            'slug' => 'other',
            'short_description' => 'No match here',
            'description' => 'Nothing',
        ]);

        $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-measure-templates?search=stress')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'stress-reset-plan');

        $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-measure-templates?search=no+match')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'other');
    }

    public function test_list_filters_by_status(): void
    {
        SystemMeasureTemplate::factory()->create();
        $draft = SystemMeasureTemplate::factory()->draft()->create();

        $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-measure-templates?status=DRAFT')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $draft->id);
    }

    public function test_list_filters_by_category(): void
    {
        SystemMeasureTemplate::factory()->create(['category' => SystemMeasureTemplate::CATEGORY_MIXED]);
        $mobility = SystemMeasureTemplate::factory()->create(['category' => SystemMeasureTemplate::CATEGORY_MOBILITY]);

        $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-measure-templates?category=MOBILITY')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mobility->id);
    }

    public function test_list_filters_by_difficulty(): void
    {
        SystemMeasureTemplate::factory()->create();
        $advanced = SystemMeasureTemplate::factory()->create(['difficulty' => SystemMeasureTemplate::DIFFICULTY_ADVANCED]);

        $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-measure-templates?difficulty=ADVANCED')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $advanced->id);
    }

    public function test_list_filters_by_is_featured(): void
    {
        SystemMeasureTemplate::factory()->create();
        $featured = SystemMeasureTemplate::factory()->featured()->create();

        $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-measure-templates?isFeatured=true')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $featured->id);

        $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-measure-templates?isFeatured=false')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_rejects_invalid_filter_values(): void
    {
        $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-measure-templates?category=SLEEP')
            ->assertUnprocessable();

        $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-measure-templates?status=NOPE')
            ->assertUnprocessable();
    }

    // ── Create ──

    public function test_platform_admin_can_create_template_with_generated_slug(): void
    {
        $response = $this->actingAs($this->platformAdmin)
            ->postJson('/api/admin/system-measure-templates', [
                'title' => 'Stress Reset',
                'shortDescription' => 'Kurz',
                'description' => 'Lang',
                'category' => SystemMeasureTemplate::CATEGORY_MINDFULNESS,
                'difficulty' => SystemMeasureTemplate::DIFFICULTY_INTERMEDIATE,
                'estimatedDurationMinutes' => 30,
                'status' => SystemMeasureTemplate::STATUS_DRAFT,
                'isFeatured' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'stress-reset')
            ->assertJsonPath('data.category', 'MINDFULNESS')
            ->assertJsonPath('data.difficulty', 'INTERMEDIATE')
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.isFeatured', true)
            ->assertJsonPath('data.exerciseCount', 0);

        $this->assertDatabaseHas('system_measure_templates', [
            'id' => $response->json('data.id'),
            'slug' => 'stress-reset',
            'created_by_user_id' => $this->platformAdmin->id,
        ]);
    }

    public function test_create_defaults_category_status_and_featured_flag(): void
    {
        $this->actingAs($this->platformAdmin)
            ->postJson('/api/admin/system-measure-templates', ['title' => 'Nur Titel'])
            ->assertCreated()
            ->assertJsonPath('data.category', 'MIXED')
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.isFeatured', false);
    }

    public function test_create_ignores_client_provided_slug(): void
    {
        $this->actingAs($this->platformAdmin)
            ->postJson('/api/admin/system-measure-templates', [
                'title' => 'Eigener Slug',
                'slug' => 'hacked-slug',
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'eigener-slug');
    }

    public function test_slug_collision_gets_numeric_suffix(): void
    {
        $this->actingAs($this->platformAdmin)
            ->postJson('/api/admin/system-measure-templates', ['title' => 'Stress Reset'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'stress-reset');

        $this->actingAs($this->platformAdmin)
            ->postJson('/api/admin/system-measure-templates', ['title' => 'Stress Reset'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'stress-reset-2');

        $this->actingAs($this->platformAdmin)
            ->postJson('/api/admin/system-measure-templates', ['title' => 'Stress Reset'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'stress-reset-3');
    }

    public function test_create_rejects_invalid_enum_values(): void
    {
        $this->actingAs($this->platformAdmin)
            ->postJson('/api/admin/system-measure-templates', [
                'title' => 'Bad Enum',
                'category' => 'GENERAL',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);

        $this->actingAs($this->platformAdmin)
            ->postJson('/api/admin/system-measure-templates', [
                'title' => 'Bad Enum',
                'difficulty' => 'IMPOSSIBLE',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['difficulty']);
    }

    public function test_create_rejects_invalid_numeric_values(): void
    {
        $this->actingAs($this->platformAdmin)
            ->postJson('/api/admin/system-measure-templates', [
                'title' => 'Bad Number',
                'estimatedDurationMinutes' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['estimatedDurationMinutes']);
    }

    // ── Update ──

    public function test_platform_admin_can_update_template(): void
    {
        $template = SystemMeasureTemplate::factory()->create();

        $this->actingAs($this->platformAdmin)
            ->patchJson("/api/admin/system-measure-templates/{$template->id}", [
                'title' => 'Neuer Titel',
                'category' => SystemMeasureTemplate::CATEGORY_BREATHING,
                'isFeatured' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Neuer Titel')
            ->assertJsonPath('data.category', 'BREATHING')
            ->assertJsonPath('data.isFeatured', true);
    }

    public function test_slug_remains_stable_when_title_changes(): void
    {
        $template = SystemMeasureTemplate::factory()->create(['title' => 'Alt', 'slug' => 'alt']);

        $this->actingAs($this->platformAdmin)
            ->patchJson("/api/admin/system-measure-templates/{$template->id}", ['title' => 'Komplett Neu'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Komplett Neu')
            ->assertJsonPath('data.slug', 'alt');
    }

    public function test_update_rejects_invalid_values(): void
    {
        $template = SystemMeasureTemplate::factory()->create();

        $this->actingAs($this->platformAdmin)
            ->patchJson("/api/admin/system-measure-templates/{$template->id}", ['status' => 'DELETED'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    // ── Detail ──

    public function test_detail_returns_exercises_ordered_by_sort_order(): void
    {
        $template = SystemMeasureTemplate::factory()->create();
        $first = SystemExercise::factory()->create();
        $second = SystemExercise::factory()->create();
        SystemMeasureTemplateExercise::factory()->create([
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => $second->id,
            'position' => 2,
        ]);
        SystemMeasureTemplateExercise::factory()->create([
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => $first->id,
            'position' => 1,
        ]);

        $this->actingAs($this->platformAdmin)
            ->getJson("/api/admin/system-measure-templates/{$template->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'slug', 'title', 'shortDescription', 'description', 'category',
                    'difficulty', 'estimatedDurationMinutes', 'status', 'isFeatured',
                    'exercises' => [
                        ['id', 'systemExerciseId', 'sortOrder', 'customTitle', 'customInstructions',
                            'customDurationMinutes', 'customSets', 'customRepetitions',
                            'customHoldSeconds', 'customFeedbackPrompt', 'isRequired',
                            'exercise' => ['id', 'slug', 'title', 'shortDescription', 'exerciseType',
                                'difficulty', 'defaultDurationMinutes', 'status', 'tags']],
                    ],
                    'createdAt', 'updatedAt',
                ],
            ])
            ->assertJsonPath('data.exercises.0.sortOrder', 1)
            ->assertJsonPath('data.exercises.0.systemExerciseId', $first->id)
            ->assertJsonPath('data.exercises.1.sortOrder', 2)
            ->assertJsonPath('data.exercises.1.systemExerciseId', $second->id);
    }

    // ── Archive ──

    public function test_platform_admin_can_archive_template(): void
    {
        $template = $this->createTemplateWithExercises(2);

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', 'ARCHIVED');
    }

    public function test_archive_is_idempotent_and_does_not_delete_or_detach(): void
    {
        $template = $this->createTemplateWithExercises(3);

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/archive")
            ->assertOk();

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', 'ARCHIVED');

        $this->assertDatabaseHas('system_measure_templates', [
            'id' => $template->id,
            'status' => SystemMeasureTemplate::STATUS_ARCHIVED,
        ]);
        $this->assertSame(3, SystemMeasureTemplateExercise::where('system_measure_template_id', $template->id)->count());
    }

    // ── Add exercise ──

    public function test_platform_admin_can_add_active_exercise_and_it_appends_at_end(): void
    {
        $template = $this->createTemplateWithExercises(2);
        $exercise = SystemExercise::factory()->create();

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/exercises", [
                'systemExerciseId' => $exercise->id,
                'customTitle' => 'Angepasst',
                'customSets' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('data.systemExerciseId', $exercise->id)
            ->assertJsonPath('data.sortOrder', 3)
            ->assertJsonPath('data.customTitle', 'Angepasst')
            ->assertJsonPath('data.customSets', 3)
            ->assertJsonPath('data.isRequired', true)
            ->assertJsonPath('data.exercise.id', $exercise->id);
    }

    public function test_adding_archived_exercise_is_rejected(): void
    {
        $template = SystemMeasureTemplate::factory()->create();
        $archived = SystemExercise::factory()->create(['status' => SystemExercise::STATUS_ARCHIVED]);

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/exercises", [
                'systemExerciseId' => $archived->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['systemExerciseId']);
    }

    public function test_adding_duplicate_exercise_is_rejected(): void
    {
        $template = SystemMeasureTemplate::factory()->create();
        $exercise = SystemExercise::factory()->create();
        SystemMeasureTemplateExercise::factory()->create([
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => $exercise->id,
            'position' => 1,
        ]);

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/exercises", [
                'systemExerciseId' => $exercise->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['systemExerciseId']);
    }

    public function test_duplicate_template_exercise_is_enforced_by_db_unique_constraint(): void
    {
        $indexes = collect(Schema::getIndexes('system_measure_template_exercises'));

        $this->assertTrue(
            $indexes->contains(fn (array $index) => $index['name'] === 'system_measure_template_exercises_template_exercise_unique' && $index['unique']),
            'Expected unique index system_measure_template_exercises_template_exercise_unique to exist.'
        );

        $template = SystemMeasureTemplate::factory()->create();
        $exercise = SystemExercise::factory()->create();
        SystemMeasureTemplateExercise::factory()->create([
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => $exercise->id,
            'position' => 1,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        // Different position, same (template, exercise) pair — only the new
        // unique constraint can reject this.
        SystemMeasureTemplateExercise::factory()->create([
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => $exercise->id,
            'position' => 2,
        ]);
    }

    public function test_adding_with_unknown_exercise_id_is_rejected(): void
    {
        $template = SystemMeasureTemplate::factory()->create();

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/exercises", [
                'systemExerciseId' => 999999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['systemExerciseId']);
    }

    public function test_adding_with_taken_sort_order_is_rejected(): void
    {
        $template = $this->createTemplateWithExercises(1);
        $exercise = SystemExercise::factory()->create();

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/exercises", [
                'systemExerciseId' => $exercise->id,
                'sortOrder' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sortOrder']);
    }

    // ── Update template exercise ──

    public function test_platform_admin_can_update_template_exercise_overrides(): void
    {
        $template = $this->createTemplateWithExercises(1);
        $templateExercise = $template->templateExercises()->first();

        $this->actingAs($this->platformAdmin)
            ->patchJson("/api/admin/system-measure-templates/{$template->id}/exercises/{$templateExercise->id}", [
                'customTitle' => 'Override',
                'customDurationMinutes' => 12,
                'isRequired' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.customTitle', 'Override')
            ->assertJsonPath('data.customDurationMinutes', 12)
            ->assertJsonPath('data.isRequired', false);
    }

    public function test_updating_template_exercise_does_not_change_underlying_exercise(): void
    {
        $template = $this->createTemplateWithExercises(1);
        $templateExercise = $template->templateExercises()->first();
        $originalTitle = $templateExercise->exercise->title;

        $this->actingAs($this->platformAdmin)
            ->patchJson("/api/admin/system-measure-templates/{$template->id}/exercises/{$templateExercise->id}", [
                'customTitle' => 'Override',
            ])
            ->assertOk();

        $this->assertDatabaseHas('system_exercises', [
            'id' => $templateExercise->system_exercise_id,
            'title' => $originalTitle,
        ]);
    }

    public function test_updating_template_exercise_of_another_template_returns_404(): void
    {
        $template = $this->createTemplateWithExercises(1);
        $otherTemplate = $this->createTemplateWithExercises(1);
        $foreignExercise = $otherTemplate->templateExercises()->first();

        $this->actingAs($this->platformAdmin)
            ->patchJson("/api/admin/system-measure-templates/{$template->id}/exercises/{$foreignExercise->id}", [
                'customTitle' => 'Override',
            ])
            ->assertNotFound();
    }

    // ── Remove template exercise ──

    public function test_platform_admin_can_remove_template_exercise_without_deleting_system_exercise(): void
    {
        $template = $this->createTemplateWithExercises(1);
        $templateExercise = $template->templateExercises()->first();
        $exerciseId = $templateExercise->system_exercise_id;

        $this->actingAs($this->platformAdmin)
            ->deleteJson("/api/admin/system-measure-templates/{$template->id}/exercises/{$templateExercise->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('system_measure_template_exercises', ['id' => $templateExercise->id]);
        $this->assertDatabaseHas('system_exercises', ['id' => $exerciseId]);
        $this->assertDatabaseHas('system_measure_templates', ['id' => $template->id]);
    }

    public function test_removing_template_exercise_of_another_template_returns_404(): void
    {
        $template = $this->createTemplateWithExercises(1);
        $otherTemplate = $this->createTemplateWithExercises(1);
        $foreignExercise = $otherTemplate->templateExercises()->first();

        $this->actingAs($this->platformAdmin)
            ->deleteJson("/api/admin/system-measure-templates/{$template->id}/exercises/{$foreignExercise->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('system_measure_template_exercises', ['id' => $foreignExercise->id]);
    }

    // ── Reorder ──

    /**
     * @return array{0: SystemMeasureTemplate, 1: array<int, int>} template and [id => position] map
     */
    private function templateWithPositions(): array
    {
        $template = $this->createTemplateWithExercises(3);
        $positions = $template->templateExercises()->pluck('position', 'id')->all();

        return [$template, $positions];
    }

    public function test_valid_complete_reorder_succeeds(): void
    {
        $template = $this->createTemplateWithExercises(3);
        $ids = $template->templateExercises()->orderBy('position')->pluck('id')->all();

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/exercises/reorder", [
                'items' => [
                    ['id' => $ids[0], 'sortOrder' => 3],
                    ['id' => $ids[1], 'sortOrder' => 1],
                    ['id' => $ids[2], 'sortOrder' => 2],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.0.id', $ids[1])
            ->assertJsonPath('data.1.id', $ids[2])
            ->assertJsonPath('data.2.id', $ids[0]);

        $this->assertSame(3, $template->templateExercises()->find($ids[0])->position);
        $this->assertSame(1, $template->templateExercises()->find($ids[1])->position);
        $this->assertSame(2, $template->templateExercises()->find($ids[2])->position);
    }

    public function test_partial_reorder_payload_is_rejected_and_positions_unchanged(): void
    {
        [$template, $positions] = $this->templateWithPositions();
        $ids = array_keys($positions);

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/exercises/reorder", [
                'items' => [
                    ['id' => $ids[0], 'sortOrder' => 2],
                    ['id' => $ids[1], 'sortOrder' => 1],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);

        $this->assertSame($positions, $template->templateExercises()->pluck('position', 'id')->all());
    }

    public function test_reorder_with_duplicate_sort_orders_is_rejected_and_positions_unchanged(): void
    {
        [$template, $positions] = $this->templateWithPositions();
        $ids = array_keys($positions);

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/exercises/reorder", [
                'items' => [
                    ['id' => $ids[0], 'sortOrder' => 1],
                    ['id' => $ids[1], 'sortOrder' => 1],
                    ['id' => $ids[2], 'sortOrder' => 2],
                ],
            ])
            ->assertUnprocessable();

        $this->assertSame($positions, $template->templateExercises()->pluck('position', 'id')->all());
    }

    public function test_reorder_with_foreign_template_exercise_id_returns_404_and_positions_unchanged(): void
    {
        [$template, $positions] = $this->templateWithPositions();
        $ids = array_keys($positions);
        $otherTemplate = $this->createTemplateWithExercises(1);
        $foreignId = $otherTemplate->templateExercises()->first()->id;

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/exercises/reorder", [
                'items' => [
                    ['id' => $ids[0], 'sortOrder' => 1],
                    ['id' => $ids[1], 'sortOrder' => 2],
                    ['id' => $foreignId, 'sortOrder' => 3],
                ],
            ])
            ->assertNotFound();

        $this->assertSame($positions, $template->templateExercises()->pluck('position', 'id')->all());
    }

    public function test_reorder_swap_does_not_violate_unique_position_constraint(): void
    {
        $template = $this->createTemplateWithExercises(2);
        $ids = $template->templateExercises()->orderBy('position')->pluck('id')->all();

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/exercises/reorder", [
                'items' => [
                    ['id' => $ids[0], 'sortOrder' => 2],
                    ['id' => $ids[1], 'sortOrder' => 1],
                ],
            ])
            ->assertOk();

        $this->assertSame(2, $template->templateExercises()->find($ids[0])->position);
        $this->assertSame(1, $template->templateExercises()->find($ids[1])->position);
    }
}
