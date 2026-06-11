<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateSystemMeasureTemplateRequest;
use App\Http\Requests\Admin\UpdateSystemMeasureTemplateRequest;
use App\Http\Resources\Admin\SystemMeasureTemplateResource;
use App\Models\SystemMeasureTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SystemMeasureTemplateController extends Controller
{
    private const COLUMN_MAP = [
        'title' => 'title',
        'shortDescription' => 'short_description',
        'description' => 'description',
        'category' => 'category',
        'difficulty' => 'difficulty',
        'estimatedDurationMinutes' => 'estimated_duration_minutes',
        'isFeatured' => 'is_featured',
        'status' => 'status',
    ];

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::in([
                SystemMeasureTemplate::STATUS_DRAFT,
                SystemMeasureTemplate::STATUS_ACTIVE,
                SystemMeasureTemplate::STATUS_ARCHIVED,
            ])],
            'category' => ['sometimes', Rule::in([
                SystemMeasureTemplate::CATEGORY_MOBILITY,
                SystemMeasureTemplate::CATEGORY_STRENGTH,
                SystemMeasureTemplate::CATEGORY_BREATHING,
                SystemMeasureTemplate::CATEGORY_MINDFULNESS,
                SystemMeasureTemplate::CATEGORY_EDUCATION,
                SystemMeasureTemplate::CATEGORY_REFLECTION,
                SystemMeasureTemplate::CATEGORY_MIXED,
            ])],
            'difficulty' => ['sometimes', Rule::in([
                SystemMeasureTemplate::DIFFICULTY_BEGINNER,
                SystemMeasureTemplate::DIFFICULTY_INTERMEDIATE,
                SystemMeasureTemplate::DIFFICULTY_ADVANCED,
            ])],
            // Query params arrive as strings, so accept the string forms too.
            'isFeatured' => ['sometimes', 'in:true,false,0,1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = SystemMeasureTemplate::withCount('templateExercises')
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

        foreach (['status' => 'status', 'category' => 'category', 'difficulty' => 'difficulty'] as $param => $column) {
            if (isset($validated[$param])) {
                $query->where($column, $validated[$param]);
            }
        }

        if (array_key_exists('isFeatured', $validated)) {
            $query->where('is_featured', filter_var($validated['isFeatured'], FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = (int) ($validated['perPage'] ?? 25);

        return SystemMeasureTemplateResource::collection(
            $query->paginate($perPage)->appends($request->query())
        );
    }

    public function store(CreateSystemMeasureTemplateRequest $request)
    {
        $data = $request->validated();

        $attributes = ['slug' => $this->generateUniqueSlug($data['title'])];
        foreach (self::COLUMN_MAP as $param => $column) {
            if (array_key_exists($param, $data)) {
                $attributes[$column] = $data[$param];
            }
        }
        $attributes['category'] = $data['category'] ?? SystemMeasureTemplate::CATEGORY_MIXED;
        $attributes['is_featured'] = $data['isFeatured'] ?? false;
        $attributes['status'] = $data['status'] ?? SystemMeasureTemplate::STATUS_ACTIVE;
        $attributes['created_by_user_id'] = $request->user()?->id;

        $template = SystemMeasureTemplate::create($attributes);
        $template->loadCount('templateExercises');

        return (new SystemMeasureTemplateResource($template))->response()->setStatusCode(201);
    }

    public function show(SystemMeasureTemplate $systemMeasureTemplate)
    {
        $systemMeasureTemplate->load('templateExercises.exercise.tags');
        $systemMeasureTemplate->loadCount('templateExercises');

        return new SystemMeasureTemplateResource($systemMeasureTemplate);
    }

    public function update(UpdateSystemMeasureTemplateRequest $request, SystemMeasureTemplate $systemMeasureTemplate)
    {
        $data = $request->validated();

        foreach (self::COLUMN_MAP as $param => $column) {
            if (array_key_exists($param, $data)) {
                $systemMeasureTemplate->{$column} = $data[$param];
            }
        }
        $systemMeasureTemplate->save();

        $systemMeasureTemplate->load('templateExercises.exercise.tags');
        $systemMeasureTemplate->loadCount('templateExercises');

        return new SystemMeasureTemplateResource($systemMeasureTemplate);
    }

    public function archive(SystemMeasureTemplate $systemMeasureTemplate)
    {
        $systemMeasureTemplate->status = SystemMeasureTemplate::STATUS_ARCHIVED;
        $systemMeasureTemplate->save();

        $systemMeasureTemplate->load('templateExercises.exercise.tags');
        $systemMeasureTemplate->loadCount('templateExercises');

        return new SystemMeasureTemplateResource($systemMeasureTemplate);
    }

    private function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'template';
        }

        $slug = $base;
        $suffix = 2;
        while (SystemMeasureTemplate::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
