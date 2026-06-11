<?php

namespace App\Http\Requests\Admin;

use App\Models\SystemExercise;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSystemExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'shortDescription' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
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
            'status' => ['sometimes', Rule::in([
                SystemExercise::STATUS_DRAFT,
                SystemExercise::STATUS_ACTIVE,
                SystemExercise::STATUS_ARCHIVED,
            ])],
            'defaultDurationMinutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'defaultSets' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'defaultRepetitions' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'defaultHoldSeconds' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'instructions' => ['sometimes', 'nullable', 'string'],
            'safetyNotes' => ['sometimes', 'nullable', 'string'],
            'contraindications' => ['sometimes', 'nullable', 'string'],
            'defaultFeedbackPrompt' => ['sometimes', 'nullable', 'string'],
            'requiresFeedback' => ['sometimes', 'boolean'],
            'tagIds' => ['sometimes', 'array'],
            'tagIds.*' => ['integer', 'exists:system_exercise_tags,id'],
        ];
    }
}
