<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\SystemExercise;
use App\Models\SystemExerciseTag;
use App\Models\SystemMeasureTemplate;
use App\Models\SystemMeasureTemplateExercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSystemExerciseTest extends TestCase
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

    // ── Authorization ──

    public function test_platform_admin_can_list_exercises(): void
    {
        SystemExercise::factory()->count(2)->create();

        $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-exercises')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_unauthenticated_user_cannot_list_exercises(): void
    {
        $this->getJson('/api/admin/system-exercises')->assertUnauthorized();
    }

    public function test_employee_cannot_list_exercises(): void
    {
        $employee = User::factory()->create(['role' => Role::EMPLOYEE]);

        $this->actingAs($employee)->getJson('/api/admin/system-exercises')->assertForbidden();
    }

    public function test_company_admin_cannot_list_exercises(): void
    {
        $companyAdmin = User::factory()->create(['role' => Role::COMPANY_ADMIN]);

        $this->actingAs($companyAdmin)->getJson('/api/admin/system-exercises')->assertForbidden();
    }

    public function test_company_manager_cannot_list_exercises(): void
    {
        $manager = User::factory()->create(['role' => Role::COMPANY_MANAGER]);

        $this->actingAs($manager)->getJson('/api/admin/system-exercises')->assertForbidden();
    }

    public function test_company_admin_cannot_create_exercise(): void
    {
        $companyAdmin = User::factory()->create(['role' => Role::COMPANY_ADMIN]);

        $this->actingAs($companyAdmin)
            ->postJson('/api/admin/system-exercises', [
                'title' => 'Forbidden',
                'exerciseType' => SystemExercise::TYPE_MOBILITY,
                'difficulty' => SystemExercise::DIFFICULTY_BEGINNER,
            ])
            ->assertForbidden();
    }

    public function test_employee_cannot_list_tags(): void
    {
        $employee = User::factory()->create(['role' => Role::EMPLOYEE]);

        $this->actingAs($employee)->getJson('/api/admin/system-exercise-tags')->assertForbidden();
    }

    // ── List ──

    public function test_list_returns_expected_fields_including_tags(): void
    {
        $exercise = SystemExercise::factory()->create();
        $tag = SystemExerciseTag::factory()->create();
        $exercise->tags()->attach($tag->id);

        $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-exercises')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'slug', 'title', 'shortDescription', 'exerciseType', 'difficulty',
                        'defaultDurationMinutes', 'status', 'tags', 'createdAt', 'updatedAt'],
                ],
                'links',
                'meta' => ['current_page', 'total'],
            ])
            ->assertJsonPath('data.0.tags.0.id', $tag->id);
    }

    public function test_list_sorts_newest_first(): void
    {
        $older = SystemExercise::factory()->create(['created_at' => now()->subDay()]);
        $newer = SystemExercise::factory()->create(['created_at' => now()]);

        $response = $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-exercises')
            ->assertOk();

        $this->assertSame($newer->id, $response->json('data.0.id'));
        $this->assertSame($older->id, $response->json('data.1.id'));
    }

    public function test_list_filters_by_search_across_fields(): void
    {
        SystemExercise::factory()->create([
            'title' => 'Nacken Stretch', 'slug' => 'nacken-stretch',
            'short_description' => 'Kurz', 'description' => 'Lang',
        ]);
        SystemExercise::factory()->create([
            'title' => 'Other', 'slug' => 'other',
            'short_description' => 'Eine Atemübung für zwischendurch', 'description' => 'Lang',
        ]);
        SystemExercise::factory()->create([
            'title' => 'Third', 'slug' => 'third',
            'short_description' => 'Kurz', 'description' => 'Langer Text über Nackenverspannung',
        ]);
        SystemExercise::factory()->create([
            'title' => 'Something else', 'slug' => 'unrelated',
            'short_description' => 'Kurz', 'description' => 'Lang',
        ]);

        $byTitle = $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-exercises?search=nacken')
            ->assertOk();
        $this->assertCount(2, $byTitle->json('data'));

        $byShortDescription = $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-exercises?search=atem')
            ->assertOk();
        $this->assertCount(1, $byShortDescription->json('data'));

        $bySlug = $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-exercises?search=unrelated')
            ->assertOk();
        $this->assertCount(1, $bySlug->json('data'));
    }

    public function test_list_filters_by_status(): void
    {
        SystemExercise::factory()->create();
        $draft = SystemExercise::factory()->draft()->create();

        $response = $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-exercises?status=DRAFT')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($draft->id, $response->json('data.0.id'));
    }

    public function test_list_filters_by_exercise_type(): void
    {
        SystemExercise::factory()->create();
        $strength = SystemExercise::factory()->strength()->create();

        $response = $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-exercises?exerciseType=STRENGTH')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($strength->id, $response->json('data.0.id'));
    }

    public function test_list_filters_by_difficulty(): void
    {
        SystemExercise::factory()->create();
        $advanced = SystemExercise::factory()->advanced()->create();

        $response = $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-exercises?difficulty=ADVANCED')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($advanced->id, $response->json('data.0.id'));
    }

    public function test_list_filters_by_tag_category_and_key_on_same_tag(): void
    {
        $neckTag = SystemExerciseTag::factory()->create([
            'category' => SystemExerciseTag::CATEGORY_BODY_REGION, 'key' => 'NECK',
        ]);
        $goalTag = SystemExerciseTag::factory()->goal()->create(['key' => 'RELAX']);

        $neckExercise = SystemExercise::factory()->create();
        $neckExercise->tags()->attach($neckTag->id);

        $goalExercise = SystemExercise::factory()->create();
        $goalExercise->tags()->attach($goalTag->id);

        // Exercise with a GOAL tag and a different BODY_REGION key must not match
        // tagCategory=BODY_REGION&tagKey=RELAX (filters apply to the same tag row).
        $mixedExercise = SystemExercise::factory()->create();
        $mixedExercise->tags()->attach([$neckTag->id, $goalTag->id]);

        $byCategory = $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-exercises?tagCategory=BODY_REGION')
            ->assertOk();
        $this->assertEqualsCanonicalizing(
            [$neckExercise->id, $mixedExercise->id],
            array_column($byCategory->json('data'), 'id')
        );

        $byKey = $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-exercises?tagKey=RELAX')
            ->assertOk();
        $this->assertEqualsCanonicalizing(
            [$goalExercise->id, $mixedExercise->id],
            array_column($byKey->json('data'), 'id')
        );

        $combinedMismatch = $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-exercises?tagCategory=BODY_REGION&tagKey=RELAX')
            ->assertOk();
        $this->assertCount(0, $combinedMismatch->json('data'));

        $combinedMatch = $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-exercises?tagCategory=BODY_REGION&tagKey=NECK')
            ->assertOk();
        $this->assertEqualsCanonicalizing(
            [$neckExercise->id, $mixedExercise->id],
            array_column($combinedMatch->json('data'), 'id')
        );
    }

    public function test_list_rejects_invalid_status_filter(): void
    {
        $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-exercises?status=BOGUS')
            ->assertStatus(422);
    }

    public function test_list_paginates(): void
    {
        SystemExercise::factory()->count(3)->create();

        $response = $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-exercises?perPage=2')
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame(3, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.last_page'));
    }

    // ── Detail ──

    public function test_platform_admin_can_view_exercise_detail(): void
    {
        $exercise = SystemExercise::factory()->create(['safety_notes' => 'Langsam bewegen.']);
        $tag = SystemExerciseTag::factory()->create();
        $exercise->tags()->attach($tag->id);

        $this->actingAs($this->platformAdmin)
            ->getJson("/api/admin/system-exercises/{$exercise->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $exercise->id)
            ->assertJsonPath('data.safetyNotes', 'Langsam bewegen.')
            ->assertJsonPath('data.tags.0.id', $tag->id)
            ->assertJsonStructure([
                'data' => ['id', 'slug', 'title', 'shortDescription', 'description', 'exerciseType',
                    'difficulty', 'defaultDurationMinutes', 'defaultSets', 'defaultRepetitions',
                    'defaultHoldSeconds', 'instructions', 'safetyNotes', 'contraindications',
                    'defaultFeedbackPrompt', 'requiresFeedback', 'status', 'tags', 'createdAt', 'updatedAt'],
            ]);
    }

    public function test_detail_returns_404_for_unknown_exercise(): void
    {
        $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-exercises/999999')
            ->assertNotFound();
    }

    // ── Create ──

    public function test_platform_admin_can_create_exercise(): void
    {
        $tags = SystemExerciseTag::factory()->count(2)->create();

        $response = $this->actingAs($this->platformAdmin)
            ->postJson('/api/admin/system-exercises', [
                'title' => 'Schulterkreisen am Schreibtisch',
                'shortDescription' => 'Lockert die Schultern',
                'exerciseType' => SystemExercise::TYPE_MOBILITY,
                'difficulty' => SystemExercise::DIFFICULTY_BEGINNER,
                'defaultDurationMinutes' => 5,
                'requiresFeedback' => true,
                'tagIds' => $tags->pluck('id')->all(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Schulterkreisen am Schreibtisch')
            ->assertJsonPath('data.slug', 'schulterkreisen-am-schreibtisch')
            ->assertJsonPath('data.status', SystemExercise::STATUS_ACTIVE);

        $exercise = SystemExercise::find($response->json('data.id'));
        $this->assertSame($this->platformAdmin->id, $exercise->created_by_user_id);
        $this->assertEqualsCanonicalizing($tags->pluck('id')->all(), $exercise->tags->pluck('id')->all());
    }

    public function test_create_generates_unique_slug_on_collision(): void
    {
        SystemExercise::factory()->create(['slug' => 'rumpf-rotation']);

        $this->actingAs($this->platformAdmin)
            ->postJson('/api/admin/system-exercises', [
                'title' => 'Rumpf Rotation',
                'exerciseType' => SystemExercise::TYPE_MOBILITY,
                'difficulty' => SystemExercise::DIFFICULTY_BEGINNER,
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'rumpf-rotation-2');
    }

    public function test_create_rejects_invalid_enum_values(): void
    {
        $this->actingAs($this->platformAdmin)
            ->postJson('/api/admin/system-exercises', [
                'title' => 'Bad enums',
                'exerciseType' => 'CARDIO',
                'difficulty' => SystemExercise::DIFFICULTY_BEGINNER,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['exerciseType']);

        $this->actingAs($this->platformAdmin)
            ->postJson('/api/admin/system-exercises', [
                'title' => 'Bad status',
                'exerciseType' => SystemExercise::TYPE_MOBILITY,
                'difficulty' => SystemExercise::DIFFICULTY_BEGINNER,
                'status' => 'DELETED',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_create_rejects_invalid_numeric_values(): void
    {
        $this->actingAs($this->platformAdmin)
            ->postJson('/api/admin/system-exercises', [
                'title' => 'Bad numbers',
                'exerciseType' => SystemExercise::TYPE_MOBILITY,
                'difficulty' => SystemExercise::DIFFICULTY_BEGINNER,
                'defaultDurationMinutes' => 0,
                'defaultSets' => -1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['defaultDurationMinutes', 'defaultSets']);
    }

    public function test_create_rejects_unknown_tag_ids(): void
    {
        $this->actingAs($this->platformAdmin)
            ->postJson('/api/admin/system-exercises', [
                'title' => 'Bad tags',
                'exerciseType' => SystemExercise::TYPE_MOBILITY,
                'difficulty' => SystemExercise::DIFFICULTY_BEGINNER,
                'tagIds' => [999999],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tagIds.0']);
    }

    // ── Update ──

    public function test_platform_admin_can_update_exercise(): void
    {
        $exercise = SystemExercise::factory()->create();

        $this->actingAs($this->platformAdmin)
            ->patchJson("/api/admin/system-exercises/{$exercise->id}", [
                'shortDescription' => 'Neue Kurzbeschreibung',
                'difficulty' => SystemExercise::DIFFICULTY_INTERMEDIATE,
            ])
            ->assertOk()
            ->assertJsonPath('data.shortDescription', 'Neue Kurzbeschreibung')
            ->assertJsonPath('data.difficulty', SystemExercise::DIFFICULTY_INTERMEDIATE);
    }

    public function test_slug_remains_stable_when_title_changes(): void
    {
        $exercise = SystemExercise::factory()->create(['slug' => 'original-slug']);

        $this->actingAs($this->platformAdmin)
            ->patchJson("/api/admin/system-exercises/{$exercise->id}", [
                'title' => 'Völlig neuer Titel',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Völlig neuer Titel')
            ->assertJsonPath('data.slug', 'original-slug');
    }

    public function test_update_syncs_tags_exactly_when_tag_ids_present(): void
    {
        $exercise = SystemExercise::factory()->create();
        $oldTags = SystemExerciseTag::factory()->count(2)->create();
        $exercise->tags()->attach($oldTags->pluck('id'));
        $newTag = SystemExerciseTag::factory()->create();

        $this->actingAs($this->platformAdmin)
            ->patchJson("/api/admin/system-exercises/{$exercise->id}", [
                'tagIds' => [$newTag->id],
            ])
            ->assertOk();

        $this->assertSame([$newTag->id], $exercise->fresh()->tags->pluck('id')->all());
    }

    public function test_update_without_tag_ids_leaves_tags_untouched(): void
    {
        $exercise = SystemExercise::factory()->create();
        $tags = SystemExerciseTag::factory()->count(2)->create();
        $exercise->tags()->attach($tags->pluck('id'));

        $this->actingAs($this->platformAdmin)
            ->patchJson("/api/admin/system-exercises/{$exercise->id}", [
                'title' => 'Nur Titel geändert',
            ])
            ->assertOk();

        $this->assertCount(2, $exercise->fresh()->tags);
    }

    public function test_update_rejects_invalid_enum_values(): void
    {
        $exercise = SystemExercise::factory()->create();

        $this->actingAs($this->platformAdmin)
            ->patchJson("/api/admin/system-exercises/{$exercise->id}", [
                'difficulty' => 'IMPOSSIBLE',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['difficulty']);
    }

    // ── Archive ──

    public function test_platform_admin_can_archive_exercise(): void
    {
        $exercise = SystemExercise::factory()->create();

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-exercises/{$exercise->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', SystemExercise::STATUS_ARCHIVED);

        $this->assertSame(SystemExercise::STATUS_ARCHIVED, $exercise->fresh()->status);
    }

    public function test_archive_is_idempotent(): void
    {
        $exercise = SystemExercise::factory()->create([
            'status' => SystemExercise::STATUS_ARCHIVED,
        ]);

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-exercises/{$exercise->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', SystemExercise::STATUS_ARCHIVED);
    }

    public function test_archive_does_not_delete_exercise_or_detach_relationships(): void
    {
        $exercise = SystemExercise::factory()->create();
        $tag = SystemExerciseTag::factory()->create();
        $exercise->tags()->attach($tag->id);

        $template = SystemMeasureTemplate::factory()->create();
        SystemMeasureTemplateExercise::create([
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => $exercise->id,
            'position' => 1,
        ]);

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/admin/system-exercises/{$exercise->id}/archive")
            ->assertOk();

        $this->assertDatabaseHas('system_exercises', ['id' => $exercise->id]);
        $this->assertDatabaseHas('system_exercise_tag', [
            'system_exercise_id' => $exercise->id,
            'system_exercise_tag_id' => $tag->id,
        ]);
        $this->assertDatabaseHas('system_measure_template_exercises', [
            'system_measure_template_id' => $template->id,
            'system_exercise_id' => $exercise->id,
        ]);
    }

    public function test_no_hard_delete_route_exists(): void
    {
        $exercise = SystemExercise::factory()->create();

        $this->actingAs($this->platformAdmin)
            ->deleteJson("/api/admin/system-exercises/{$exercise->id}")
            ->assertStatus(405);
    }

    // ── Tags ──

    public function test_platform_admin_can_list_tags_sorted(): void
    {
        SystemExerciseTag::factory()->goal()->create(['key' => 'G1', 'label' => 'Ziel', 'sort_order' => 1]);
        SystemExerciseTag::factory()->create(['key' => 'B2', 'label' => 'Zeta', 'sort_order' => 2]);
        SystemExerciseTag::factory()->create(['key' => 'B1B', 'label' => 'Beta', 'sort_order' => 1]);
        SystemExerciseTag::factory()->create(['key' => 'B1A', 'label' => 'Alpha', 'sort_order' => 1]);

        $response = $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-exercise-tags')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'category', 'key', 'label', 'description', 'sortOrder', 'isActive'],
                ],
            ]);

        $this->assertSame(['B1A', 'B1B', 'B2', 'G1'], array_column($response->json('data'), 'key'));
    }

    public function test_tag_list_filters_work(): void
    {
        SystemExerciseTag::factory()->create(['key' => 'NECK', 'label' => 'Nacken']);
        SystemExerciseTag::factory()->goal()->create(['key' => 'RELAX', 'label' => 'Entspannung']);
        SystemExerciseTag::factory()->goal()->create(['key' => 'OLD', 'label' => 'Alt', 'is_active' => false]);

        $byCategory = $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-exercise-tags?category=GOAL')
            ->assertOk();
        $this->assertCount(2, $byCategory->json('data'));

        $byActive = $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-exercise-tags?isActive=0')
            ->assertOk();
        $this->assertCount(1, $byActive->json('data'));
        $this->assertSame('OLD', $byActive->json('data.0.key'));

        $bySearch = $this->actingAs($this->platformAdmin)
            ->getJson('/api/admin/system-exercise-tags?search=nacken')
            ->assertOk();
        $this->assertCount(1, $bySearch->json('data'));
        $this->assertSame('NECK', $bySearch->json('data.0.key'));
    }

    public function test_no_tag_mutation_routes_exist(): void
    {
        $tag = SystemExerciseTag::factory()->create();

        $this->actingAs($this->platformAdmin)
            ->postJson('/api/admin/system-exercise-tags', ['key' => 'NEW'])
            ->assertStatus(405);

        $this->actingAs($this->platformAdmin)
            ->patchJson("/api/admin/system-exercise-tags/{$tag->id}", ['label' => 'X'])
            ->assertNotFound();

        $this->actingAs($this->platformAdmin)
            ->deleteJson("/api/admin/system-exercise-tags/{$tag->id}")
            ->assertNotFound();
    }
}
