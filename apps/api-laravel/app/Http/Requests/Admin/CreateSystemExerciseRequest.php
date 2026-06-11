<?php

namespace App\Http\Requests\Admin;

use App\Models\SystemExercise;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSystemExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'shortDescription' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'exerciseType' => ['required', Rule::in([
                SystemExercise::TYPE_MOBILITY,
                SystemExercise::TYPE_STRENGTH,
                SystemExercise::TYPE_BREATHING,
                SystemExercise::TYPE_MINDFULNESS,
                SystemExercise::TYPE_EDUCATION,
                SystemExercise::TYPE_REFLECTION,
            ])],
            'difficulty' => ['required', Rule::in([
                SystemExercise::DIFFICULTY_BEGINNER,
                SystemExercise::DIFFICULTY_INTERMEDIATE,
                SystemExercise::DIFFICULTY_ADVANCED,
            ])],
            'status' => ['sometimes', Rule::in([
                SystemExercise::STATUS_DRAFT,
                SystemExercise::STATUS_ACTIVE,
                SystemExercise::STATUS_ARCHIVED,
            ])],
            'defaultDurationMinutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'defaultSets' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'defaultRepetitions' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'defaultHoldSeconds' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'instructions' => ['nullable', 'string'],
            'safetyNotes' => ['nullable', 'string'],
            'contraindications' => ['nullable', 'string'],
            'defaultFeedbackPrompt' => ['nullable', 'string'],
            'requiresFeedback' => ['sometimes', 'boolean'],
            'tagIds' => ['sometimes', 'array'],
            'tagIds.*' => ['integer', 'exists:system_exercise_tags,id'],
        ];
    }
}
