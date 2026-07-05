<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateSystemExerciseRequest;
use App\Http\Requests\Admin\UpdateSystemExerciseRequest;
use App\Http\Resources\Admin\SystemExerciseResource;
use App\Models\SystemExercise;
use App\Models\SystemExerciseTag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SystemExerciseController extends Controller
{
    private const COLUMN_MAP = [
        'title' => 'title',
        'shortDescription' => 'short_description',
        'description' => 'description',
        'exerciseType' => 'exercise_type',
        'difficulty' => 'difficulty',
        'defaultDurationMinutes' => 'default_duration_minutes',
        'defaultSets' => 'default_sets',
        'defaultRepetitions' => 'default_repetitions',
        'defaultHoldSeconds' => 'default_hold_seconds',
        'instructions' => 'instructions',
        'safetyNotes' => 'safety_notes',
        'contraindications' => 'contraindications',
        'defaultFeedbackPrompt' => 'default_feedback_prompt',
        'requiresFeedback' => 'requires_feedback',
        'mainPictogramPath' => 'main_pictogram_path',
        'mainPictogramAlt' => 'main_pictogram_alt',
        'locationTags' => 'location_tags',
        'postureTags' => 'posture_tags',
        'requiresFloor' => 'requires_floor',
        'defaultEffort' => 'default_effort',
        'status' => 'status',
    ];

    /** Normalize camelCase step keys from the API to the snake_case shape stored in jsonb. */
    private function normalizeSteps(?array $steps): ?array
    {
        if ($steps === null) {
            return null;
        }

        return array_values(array_map(fn (array $step) => [
            'text' => $step['text'],
            'pictogram_path' => $step['pictogramPath'] ?? null,
            'alt' => $step['alt'] ?? null,
        ], $steps));
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::in([
                SystemExercise::STATUS_DRAFT,
                SystemExercise::STATUS_ACTIVE,
                SystemExercise::STATUS_ARCHIVED,
            ])],
            'exerciseType' => ['sometimes', Rule::in([
                SystemExercise::TYPE_MOBILITY,
                SystemExercise::TYPE_STRENGTH,
                SystemExercise::TYPE_BREATHING,
                SystemExercise::TYPE_MINDFULNESS,
                SystemExercise::TYPE_EDUCATION,
                SystemExercise::TYPE_REFLECTION,
            ])],
            'difficulty' => ['sometimes', Rule::in([
                SystemExercise::DIFFICULTY_BEGINNER,
                SystemExercise::DIFFICULTY_INTERMEDIATE,
                SystemExercise::DIFFICULTY_ADVANCED,
            ])],
            'tagCategory' => ['sometimes', Rule::in([
                SystemExerciseTag::CATEGORY_BODY_REGION,
                SystemExerciseTag::CATEGORY_GOAL,
                SystemExerciseTag::CATEGORY_SETTING,
                SystemExerciseTag::CATEGORY_EQUIPMENT,
                SystemExerciseTag::CATEGORY_CONTRAINDICATION,
                SystemExerciseTag::CATEGORY_PERSONA_HINT,
                SystemExerciseTag::CATEGORY_HEALTH_FOCUS,
            ])],
            'tagKey' => ['sometimes', 'string'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = SystemExercise::with('tags')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(title) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(short_description) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(slug) LIKE ?', [$term]);
            });
        }

        foreach (['status' => 'status', 'exerciseType' => 'exercise_type', 'difficulty' => 'difficulty'] as $param => $column) {
            if (isset($validated[$param])) {
                $query->where($column, $validated[$param]);
            }
        }

        $tagCategory = $validated['tagCategory'] ?? null;
        $tagKey = $validated['tagKey'] ?? null;
        if ($tagCategory !== null || $tagKey !== null) {
            // Both filters must match the same tag row.
            $query->whereHas('tags', function ($q) use ($tagCategory, $tagKey) {
                if ($tagCategory !== null) {
                    $q->where('category', $tagCategory);
                }
                if ($tagKey !== null) {
                    $q->where('key', $tagKey);
                }
            });
        }

        $perPage = (int) ($validated['perPage'] ?? 25);

        return SystemExerciseResource::collection(
            $query->paginate($perPage)->appends($request->query())
        );
    }

    public function store(CreateSystemExerciseRequest $request)
    {
        $data = $request->validated();

        $attributes = ['slug' => $this->generateUniqueSlug($data['title'])];
        foreach (self::COLUMN_MAP as $param => $column) {
            if (array_key_exists($param, $data)) {
                $attributes[$column] = $data[$param];
            }
        }
        $attributes['requires_feedback'] = $data['requiresFeedback'] ?? true;
        $attributes['status'] = $data['status'] ?? SystemExercise::STATUS_ACTIVE;
        $attributes['created_by_user_id'] = $request->user()?->id;
        if (array_key_exists('steps', $data)) {
            $attributes['steps'] = $this->normalizeSteps($data['steps']);
        }

        $exercise = SystemExercise::create($attributes);
        $exercise->tags()->sync($data['tagIds'] ?? []);
        $exercise->load('tags');

        return (new SystemExerciseResource($exercise))->response()->setStatusCode(201);
    }

    public function show(SystemExercise $systemExercise)
    {
        $systemExercise->load('tags');

        return new SystemExerciseResource($systemExercise);
    }

    public function update(UpdateSystemExerciseRequest $request, SystemExercise $systemExercise)
    {
        $data = $request->validated();

        foreach (self::COLUMN_MAP as $param => $column) {
            if (array_key_exists($param, $data)) {
                $systemExercise->{$column} = $data[$param];
            }
        }
        if (array_key_exists('steps', $data)) {
            $systemExercise->steps = $this->normalizeSteps($data['steps']);
        }
        $systemExercise->save();

        if (array_key_exists('tagIds', $data)) {
            $systemExercise->tags()->sync($data['tagIds'] ?? []);
        }

        $systemExercise->load('tags');

        return new SystemExerciseResource($systemExercise);
    }

    public function archive(SystemExercise $systemExercise)
    {
        $systemExercise->status = SystemExercise::STATUS_ARCHIVED;
        $systemExercise->save();

        $systemExercise->load('tags');

        return new SystemExerciseResource($systemExercise);
    }

    private function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'exercise';
        }

        $slug = $base;
        $suffix = 2;
        while (SystemExercise::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
