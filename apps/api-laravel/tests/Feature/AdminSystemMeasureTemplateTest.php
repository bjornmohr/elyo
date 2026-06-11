<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\SystemExercise;
use App\Models\SystemMeasureTemplate;
use App\Models\SystemMeasureTemplateExercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_platform_admin_and_support_can_list_templates(): void
    {
        SystemMeasureTemplate::factory()->count(2)->create();
        $support = User::factory()->platformAdmin()->create(['role' => Role::ELYO_SUPPORT]);

        $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-measure-templates')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($support)
            ->getJson('/api/admin/system-measure-templates')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_non_platform_users_cannot_access_templates(): void
    {
        $this->getJson('/api/admin/system-measure-templates')->assertUnauthorized();

        foreach ([Role::COMPANY_OWNER, Role::COMPANY_ADMIN, Role::COMPANY_MANAGER, Role::EMPLOYEE] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->getJson('/api/admin/system-measure-templates')->assertForbidden();
        }
    }

    public function test_list_returns_expected_fields_filters_and_sort_order(): void
    {
        $older = SystemMeasureTemplate::factory()->create([
            'title' => 'Mobility Reset',
            'slug' => 'mobility-reset',
            'short_description' => 'Desk movement',
            'description' => 'For shoulders',
            'category' => SystemMeasureTemplate::CATEGORY_MOBILITY,
            'difficulty' => SystemMeasureTemplate::DIFFICULTY_BEGINNER,
            'status' => SystemMeasureTemplate::STATUS_DRAFT,
            'is_featured' => true,
            'created_at' => now()->subDay(),
        ]);
        $newer = SystemMeasureTemplate::factory()->create([
            'title' => 'Breathing Plan',
            'category' => SystemMeasureTemplate::CATEGORY_BREATHING,
            'difficulty' => SystemMeasureTemplate::DIFFICULTY_ADVANCED,
            'status' => SystemMeasureTemplate::STATUS_ACTIVE,
            'is_featured' => false,
            'created_at' => now(),
        ]);
        SystemMeasureTemplateExercise::create([
            'system_measure_template_id' => $older->id,
            'system_exercise_id' => SystemExercise::factory()->create()->id,
            'position' => 1,
        ]);

        $response = $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-measure-templates')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'slug', 'title', 'shortDescription', 'category', 'difficulty',
                        'estimatedDurationMinutes', 'status', 'isFeatured', 'exerciseCount', 'createdAt', 'updatedAt'],
                ],
                'links',
                'meta' => ['current_page', 'total'],
            ]);

        $this->assertSame($newer->id, $response->json('data.0.id'));
        $this->assertSame($older->id, $response->json('data.1.id'));
        $this->assertSame(1, $response->json('data.1.exerciseCount'));

        $this->assertSame($older->id, $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-measure-templates?search=shoulders')
            ->json('data.0.id'));
        $this->assertSame($older->id, $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-measure-templates?status=DRAFT&category=MOBILITY&difficulty=BEGINNER&isFeatured=true')
            ->json('data.0.id'));
    }

    public function test_list_rejects_invalid_filters(): void
    {
        $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-measure-templates?category=MEDICAL')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    public function test_create_generates_slug_sets_creator_and_rejects_bad_payloads(): void
    {
        SystemMeasureTemplate::factory()->create(['slug' => 'stress-reset']);

        $response = $this->actingAs($this->platformAdmin)
            ->postJson('/api/admin/system-measure-templates', [
                'title' => 'Stress Reset',
                'shortDescription' => 'Short',
                'category' => SystemMeasureTemplate::CATEGORY_MIXED,
                'difficulty' => SystemMeasureTemplate::DIFFICULTY_INTERMEDIATE,
                'estimatedDurationMinutes' => 20,
                'isFeatured' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'stress-reset-2')
            ->assertJsonPath('data.status', SystemMeasureTemplate::STATUS_DRAFT)
            ->assertJsonPath('data.isFeatured', true);

        $template = SystemMeasureTemplate::findOrFail($response->json('data.id'));
        $this->assertSame($this->platformAdmin->id, $template->created_by_user_id);

        $this->actingAs($this->platformAdmin)
            ->postJson('/api/admin/system-measure-templates', [
                'title' => 'Bad',
                'category' => 'MEDICAL',
                'difficulty' => 'IMPOSSIBLE',
                'estimatedDurationMinutes' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category', 'difficulty', 'estimatedDurationMinutes']);
    }

    public function test_update_keeps_slug_stable(): void
    {
        $template = SystemMeasureTemplate::factory()->create(['slug' => 'original']);

        $this->actingAs($this->platformAdmin)
            ->patchJson("/api/admin/system-measure-templates/{$template->id}", [
                'title' => 'New Title',
                'status' => SystemMeasureTemplate::STATUS_ACTIVE,
                'isFeatured' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'New Title')
            ->assertJsonPath('data.slug', 'original')
            ->assertJsonPath('data.isFeatured', true);
    }

    public function test_archive_is_idempotent_and_keeps_exercises(): void
    {
        $template = SystemMeasureTemplate::factory()->create();
        $exercise = SystemExercise::factory()->create(['instructions' => 'Original instructions']);
        SystemMeasureTemplateExercise::create([
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => $exercise->id,
            'position' => 1,
        ]);

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', SystemMeasureTemplate::STATUS_ARCHIVED);

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', SystemMeasureTemplate::STATUS_ARCHIVED);

        $this->assertDatabaseHas('system_measure_templates', ['id' => $template->id]);
        $this->assertDatabaseHas('system_measure_template_exercises', [
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => $exercise->id,
        ]);
    }

    public function test_detail_returns_ordered_template_exercises_with_nested_exercise(): void
    {
        $template = SystemMeasureTemplate::factory()->create();
        $second = SystemExercise::factory()->create(['title' => 'Second']);
        $first = SystemExercise::factory()->create(['title' => 'First']);
        SystemMeasureTemplateExercise::create([
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => $second->id,
            'position' => 2,
        ]);
        SystemMeasureTemplateExercise::create([
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => $first->id,
            'position' => 1,
        ]);

        $this->actingAs($this->platformAdmin)
            ->getJson("/api/admin/system-measure-templates/{$template->id}")
            ->assertOk()
            ->assertJsonPath('data.exercises.0.exercise.title', 'First')
            ->assertJsonPath('data.exercises.1.exercise.title', 'Second');
    }

    public function test_template_exercise_lifecycle_validation_and_relationship_delete(): void
    {
        $template = SystemMeasureTemplate::factory()->create();
        $exercise = SystemExercise::factory()->create(['instructions' => 'Original instructions']);
        $archivedExercise = SystemExercise::factory()->create(['status' => SystemExercise::STATUS_ARCHIVED]);

        $created = $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/exercises", [
                'systemExerciseId' => $exercise->id,
                'customTitle' => 'Template title',
                'isRequired' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('data.sortOrder', 1)
            ->assertJsonPath('data.customTitle', 'Template title')
            ->assertJsonPath('data.isRequired', false);

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/exercises", [
                'systemExerciseId' => $archivedExercise->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['systemExerciseId']);

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/exercises", [
                'systemExerciseId' => $exercise->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['systemExerciseId']);

        $templateExerciseId = $created->json('data.id');
        $this->actingAs($this->platformAdmin)
            ->patchJson("/api/admin/system-measure-templates/{$template->id}/exercises/{$templateExerciseId}", [
                'customInstructions' => 'Override only',
                'customDurationMinutes' => 12,
                'isRequired' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.customInstructions', 'Override only')
            ->assertJsonPath('data.customDurationMinutes', 12)
            ->assertJsonPath('data.isRequired', true);

        $this->assertSame('Original instructions', $exercise->fresh()->instructions);

        $this->actingAs($this->platformAdmin)
            ->deleteJson("/api/admin/system-measure-templates/{$template->id}/exercises/{$templateExerciseId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('system_measure_template_exercises', ['id' => $templateExerciseId]);
        $this->assertDatabaseHas('system_exercises', ['id' => $exercise->id]);
    }

    public function test_child_rows_from_other_templates_return_not_found(): void
    {
        $template = SystemMeasureTemplate::factory()->create();
        $otherTemplate = SystemMeasureTemplate::factory()->create();
        $otherRow = SystemMeasureTemplateExercise::create([
            'system_measure_template_id' => $otherTemplate->id,
            'system_exercise_id' => SystemExercise::factory()->create()->id,
            'position' => 1,
        ]);

        $this->actingAs($this->platformAdmin)
            ->patchJson("/api/admin/system-measure-templates/{$template->id}/exercises/{$otherRow->id}", [
                'customTitle' => 'Wrong parent',
            ])
            ->assertNotFound();

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/exercises/reorder", [
                'items' => [['id' => $otherRow->id, 'sortOrder' => 1]],
            ])
            ->assertNotFound();
    }

    public function test_partial_reorder_is_rejected_and_positions_remain_unchanged(): void
    {
        $template = SystemMeasureTemplate::factory()->create();
        $first = SystemMeasureTemplateExercise::create([
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => SystemExercise::factory()->create()->id,
            'position' => 1,
        ]);
        $second = SystemMeasureTemplateExercise::create([
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => SystemExercise::factory()->create()->id,
            'position' => 2,
        ]);
        $third = SystemMeasureTemplateExercise::create([
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => SystemExercise::factory()->create()->id,
            'position' => 3,
        ]);

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/exercises/reorder", [
                'items' => [
                    ['id' => $first->id, 'sortOrder' => 2],
                    ['id' => $third->id, 'sortOrder' => 1],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);

        $this->assertSame(1, $first->fresh()->position);
        $this->assertSame(2, $second->fresh()->position);
        $this->assertSame(3, $third->fresh()->position);
    }

    public function test_reorder_rejects_duplicate_sort_order_and_positions_remain_unchanged(): void
    {
        $template = SystemMeasureTemplate::factory()->create();
        $first = SystemMeasureTemplateExercise::create([
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => SystemExercise::factory()->create()->id,
            'position' => 1,
        ]);
        $second = SystemMeasureTemplateExercise::create([
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => SystemExercise::factory()->create()->id,
            'position' => 2,
        ]);
        $third = SystemMeasureTemplateExercise::create([
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => SystemExercise::factory()->create()->id,
            'position' => 3,
        ]);

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/exercises/reorder", [
                'items' => [
                    ['id' => $first->id, 'sortOrder' => 1],
                    ['id' => $second->id, 'sortOrder' => 1],
                    ['id' => $third->id, 'sortOrder' => 3],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.1.sortOrder']);

        $this->assertSame(1, $first->fresh()->position);
        $this->assertSame(2, $second->fresh()->position);
        $this->assertSame(3, $third->fresh()->position);
    }

    public function test_reorder_updates_positions_transaction_safely(): void
    {
        $template = SystemMeasureTemplate::factory()->create();
        $first = SystemMeasureTemplateExercise::create([
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => SystemExercise::factory()->create()->id,
            'position' => 1,
        ]);
        $second = SystemMeasureTemplateExercise::create([
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => SystemExercise::factory()->create()->id,
            'position' => 2,
        ]);
        $otherTemplate = SystemMeasureTemplate::factory()->create();
        $otherRow = SystemMeasureTemplateExercise::create([
            'system_measure_template_id' => $otherTemplate->id,
            'system_exercise_id' => SystemExercise::factory()->create()->id,
            'position' => 1,
        ]);

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/exercises/reorder", [
                'items' => [
                    ['id' => $otherRow->id, 'sortOrder' => 1],
                    ['id' => $first->id, 'sortOrder' => 2],
                ],
            ])
            ->assertNotFound();

        $this->assertSame(1, $first->fresh()->position);
        $this->assertSame(2, $second->fresh()->position);

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-measure-templates/{$template->id}/exercises/reorder", [
                'items' => [
                    ['id' => $first->id, 'sortOrder' => 2],
                    ['id' => $second->id, 'sortOrder' => 1],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.0.sortOrder', 1);

        $this->assertSame(2, $first->fresh()->position);
        $this->assertSame(1, $second->fresh()->position);
    }

    public function test_no_hard_delete_route_exists_for_templates(): void
    {
        $template = SystemMeasureTemplate::factory()->create();

        $this->actingAs($this->platformAdmin)
            ->deleteJson("/api/admin/system-measure-templates/{$template->id}")
            ->assertStatus(405);
    }
}
