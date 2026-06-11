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
        'status' => 'status',
        'isFeatured' => 'is_featured',
    ];

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::in(CreateSystemMeasureTemplateRequest::statuses())],
            'category' => ['sometimes', Rule::in(CreateSystemMeasureTemplateRequest::categories())],
            'difficulty' => ['sometimes', Rule::in(CreateSystemMeasureTemplateRequest::difficulties())],
            'isFeatured' => ['sometimes', Rule::in(['true', 'false', '1', '0', 1, 0, true, false])],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = SystemMeasureTemplate::query()
            ->withCount('templateExercises')
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

        foreach (['status', 'category', 'difficulty'] as $field) {
            if (isset($validated[$field])) {
                $query->where($field, $validated[$field]);
            }
        }

        if (array_key_exists('isFeatured', $validated)) {
            $query->where('is_featured', $request->boolean('isFeatured'));
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
        $attributes['difficulty'] = $data['difficulty'] ?? SystemMeasureTemplate::DIFFICULTY_BEGINNER;
        $attributes['status'] = $data['status'] ?? SystemMeasureTemplate::STATUS_DRAFT;
        $attributes['is_featured'] = $data['isFeatured'] ?? false;
        $attributes['created_by_user_id'] = $request->user()?->id;

        $template = SystemMeasureTemplate::create($attributes);
        $template->loadCount('templateExercises');

        return (new SystemMeasureTemplateResource($template))->response()->setStatusCode(201);
    }

    public function show(SystemMeasureTemplate $systemMeasureTemplate)
    {
        $systemMeasureTemplate->load([
            'templateExercises.exercise.tags',
        ])->loadCount('templateExercises');

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
        $systemMeasureTemplate->loadCount('templateExercises');

        return new SystemMeasureTemplateResource($systemMeasureTemplate);
    }

    public function archive(SystemMeasureTemplate $systemMeasureTemplate)
    {
        $systemMeasureTemplate->status = SystemMeasureTemplate::STATUS_ARCHIVED;
        $systemMeasureTemplate->save();
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
