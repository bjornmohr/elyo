<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateSystemMeasureTemplateExerciseRequest;
use App\Http\Requests\Admin\ReorderSystemMeasureTemplateExercisesRequest;
use App\Http\Requests\Admin\UpdateSystemMeasureTemplateExerciseRequest;
use App\Http\Resources\Admin\SystemMeasureTemplateExerciseResource;
use App\Models\SystemExercise;
use App\Models\SystemMeasureTemplate;
use App\Models\SystemMeasureTemplateExercise;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SystemMeasureTemplateExerciseController extends Controller
{
    private const COLUMN_MAP = [
        'sortOrder' => 'position',
        'customTitle' => 'custom_title',
        'customInstructions' => 'custom_instructions',
        'customDurationMinutes' => 'custom_duration_minutes',
        'customSets' => 'custom_sets',
        'customRepetitions' => 'custom_repetitions',
        'customHoldSeconds' => 'custom_hold_seconds',
        'customFeedbackPrompt' => 'custom_feedback_prompt',
        'isRequired' => 'is_required',
    ];

    public function store(CreateSystemMeasureTemplateExerciseRequest $request, SystemMeasureTemplate $systemMeasureTemplate)
    {
        $data = $request->validated();
        $exercise = SystemExercise::findOrFail($data['systemExerciseId']);

        if ($exercise->status === SystemExercise::STATUS_ARCHIVED) {
            throw ValidationException::withMessages([
                'systemExerciseId' => ['Archived system exercises cannot be added to templates.'],
            ]);
        }

        if ($systemMeasureTemplate->templateExercises()->where('system_exercise_id', $exercise->id)->exists()) {
            throw ValidationException::withMessages([
                'systemExerciseId' => ['This exercise is already in the template.'],
            ]);
        }

        $position = $data['sortOrder'] ?? ((int) $systemMeasureTemplate->templateExercises()->max('position') + 1);
        $this->ensurePositionAvailable($systemMeasureTemplate, $position);

        $attributes = [
            'system_measure_template_id' => $systemMeasureTemplate->id,
            'system_exercise_id' => $exercise->id,
            'position' => $position,
            'is_required' => $data['isRequired'] ?? true,
        ];

        foreach (self::COLUMN_MAP as $param => $column) {
            if ($param !== 'sortOrder' && array_key_exists($param, $data)) {
                $attributes[$column] = $data[$param];
            }
        }

        $templateExercise = SystemMeasureTemplateExercise::create($attributes);
        $templateExercise->load('exercise.tags');

        return (new SystemMeasureTemplateExerciseResource($templateExercise))->response()->setStatusCode(201);
    }

    public function update(UpdateSystemMeasureTemplateExerciseRequest $request, SystemMeasureTemplate $systemMeasureTemplate, SystemMeasureTemplateExercise $templateExercise)
    {
        $this->abortIfWrongTemplate($systemMeasureTemplate, $templateExercise);

        $data = $request->validated();
        if (array_key_exists('sortOrder', $data)) {
            $this->ensurePositionAvailable($systemMeasureTemplate, $data['sortOrder'], $templateExercise->id);
        }

        foreach (self::COLUMN_MAP as $param => $column) {
            if (array_key_exists($param, $data)) {
                $templateExercise->{$column} = $data[$param];
            }
        }

        $templateExercise->save();
        $templateExercise->load('exercise.tags');

        return new SystemMeasureTemplateExerciseResource($templateExercise);
    }

    public function destroy(SystemMeasureTemplate $systemMeasureTemplate, SystemMeasureTemplateExercise $templateExercise)
    {
        $this->abortIfWrongTemplate($systemMeasureTemplate, $templateExercise);

        $templateExercise->delete();

        return response()->noContent();
    }

    public function reorder(ReorderSystemMeasureTemplateExercisesRequest $request, SystemMeasureTemplate $systemMeasureTemplate)
    {
        $items = collect($request->validated('items'));
        $ids = $items->pluck('id')->all();

        $ownedCount = $systemMeasureTemplate->templateExercises()->whereIn('id', $ids)->count();
        if ($ownedCount !== count($ids)) {
            abort(404);
        }

        DB::transaction(function () use ($systemMeasureTemplate, $items) {
            $offset = ((int) $systemMeasureTemplate->templateExercises()->max('position')) + 100000;

            foreach ($items->values() as $index => $item) {
                $systemMeasureTemplate->templateExercises()
                    ->where('id', $item['id'])
                    ->update(['position' => $offset + $index + 1]);
            }

            foreach ($items as $item) {
                $systemMeasureTemplate->templateExercises()
                    ->where('id', $item['id'])
                    ->update(['position' => $item['sortOrder']]);
            }
        });

        $systemMeasureTemplate->load('templateExercises.exercise.tags');

        return SystemMeasureTemplateExerciseResource::collection($systemMeasureTemplate->templateExercises);
    }

    private function abortIfWrongTemplate(SystemMeasureTemplate $template, SystemMeasureTemplateExercise $templateExercise): void
    {
        if ($templateExercise->system_measure_template_id !== $template->id) {
            abort(404);
        }
    }

    private function ensurePositionAvailable(SystemMeasureTemplate $template, int $position, ?int $ignoreId = null): void
    {
        $query = $template->templateExercises()->where('position', $position);
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'sortOrder' => ['This sort order is already used in the template.'],
            ]);
        }
    }
}
