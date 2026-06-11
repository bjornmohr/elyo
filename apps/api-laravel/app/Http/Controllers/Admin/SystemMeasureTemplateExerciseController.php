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
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SystemMeasureTemplateExerciseController extends Controller
{
    private const OVERRIDE_COLUMN_MAP = [
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
                'systemExerciseId' => 'Archived system exercises cannot be added to a template.',
            ]);
        }

        if ($systemMeasureTemplate->templateExercises()->where('system_exercise_id', $exercise->id)->exists()) {
            throw ValidationException::withMessages([
                'systemExerciseId' => 'This system exercise is already part of the template.',
            ]);
        }

        if (array_key_exists('sortOrder', $data)) {
            $this->ensurePositionIsFree($systemMeasureTemplate, (int) $data['sortOrder']);
            $position = (int) $data['sortOrder'];
        } else {
            $position = (int) $systemMeasureTemplate->templateExercises()->max('position') + 1;
        }

        $attributes = [
            'system_exercise_id' => $exercise->id,
            'position' => $position,
            'is_required' => $data['isRequired'] ?? true,
        ];
        foreach (self::OVERRIDE_COLUMN_MAP as $param => $column) {
            if ($param !== 'isRequired' && array_key_exists($param, $data)) {
                $attributes[$column] = $data[$param];
            }
        }

        try {
            $templateExercise = $systemMeasureTemplate->templateExercises()->create($attributes);
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent request won the race; map the DB constraint to the
            // same 422 the application-level checks produce instead of a 500.
            if (str_contains($e->getMessage(), 'template_exercise_unique')) {
                throw ValidationException::withMessages([
                    'systemExerciseId' => 'This system exercise is already part of the template.',
                ]);
            }

            throw ValidationException::withMessages([
                'sortOrder' => 'This sort order is already used in the template. Use the reorder endpoint to move multiple exercises.',
            ]);
        }

        $templateExercise->load('exercise.tags');

        return (new SystemMeasureTemplateExerciseResource($templateExercise))->response()->setStatusCode(201);
    }

    public function update(
        UpdateSystemMeasureTemplateExerciseRequest $request,
        SystemMeasureTemplate $systemMeasureTemplate,
        SystemMeasureTemplateExercise $templateExercise
    ) {
        $this->ensureBelongsToTemplate($systemMeasureTemplate, $templateExercise);

        $data = $request->validated();

        if (array_key_exists('sortOrder', $data) && (int) $data['sortOrder'] !== $templateExercise->position) {
            $this->ensurePositionIsFree($systemMeasureTemplate, (int) $data['sortOrder']);
            $templateExercise->position = (int) $data['sortOrder'];
        }

        foreach (self::OVERRIDE_COLUMN_MAP as $param => $column) {
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
        $this->ensureBelongsToTemplate($systemMeasureTemplate, $templateExercise);

        $templateExercise->delete();

        return response()->noContent();
    }

    public function reorder(ReorderSystemMeasureTemplateExercisesRequest $request, SystemMeasureTemplate $systemMeasureTemplate)
    {
        $items = $request->validated()['items'];
        $submittedIds = collect($items)->pluck('id')->map(fn ($id) => (int) $id);

        DB::transaction(function () use ($systemMeasureTemplate, $items, $submittedIds) {
            // Lock the template's rows so a concurrent add/remove cannot make
            // the complete-set check stale before the position writes.
            $lockedRows = $systemMeasureTemplate->templateExercises()->lockForUpdate()->get();
            $existingIds = $lockedRows->pluck('id');

            if ($submittedIds->diff($existingIds)->isNotEmpty()) {
                abort(404, 'Template exercise not found in this template.');
            }

            if ($existingIds->diff($submittedIds)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Reorder requires the complete set of template exercise IDs for this template.',
                ]);
            }

            // Shift past both current positions and submitted sortOrders so the
            // per-row final writes never hit the (template_id, position) unique key.
            $offset = max(
                (int) $lockedRows->max('position'),
                (int) collect($items)->max('sortOrder')
            ) + 1;

            $systemMeasureTemplate->templateExercises()->update([
                'position' => DB::raw('position + '.$offset),
            ]);

            foreach ($items as $item) {
                $systemMeasureTemplate->templateExercises()
                    ->where('id', (int) $item['id'])
                    ->update(['position' => (int) $item['sortOrder']]);
            }
        });

        $exercises = $systemMeasureTemplate->templateExercises()->with('exercise.tags')->get();

        return SystemMeasureTemplateExerciseResource::collection($exercises);
    }

    private function ensureBelongsToTemplate(SystemMeasureTemplate $template, SystemMeasureTemplateExercise $templateExercise): void
    {
        if ($templateExercise->system_measure_template_id !== $template->id) {
            abort(404);
        }
    }

    private function ensurePositionIsFree(SystemMeasureTemplate $template, int $position): void
    {
        if ($template->templateExercises()->where('position', $position)->exists()) {
            throw ValidationException::withMessages([
                'sortOrder' => 'This sort order is already used in the template. Use the reorder endpoint to move multiple exercises.',
            ]);
        }
    }
}
