<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateSystemMeasureTemplateExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'systemExerciseId' => ['required', 'integer', 'exists:system_exercises,id'],
            'sortOrder' => ['sometimes', 'integer', 'min:1'],
            'customTitle' => ['nullable', 'string', 'max:255'],
            'customInstructions' => ['nullable', 'string'],
            'customDurationMinutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'customSets' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'customRepetitions' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'customHoldSeconds' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'customFeedbackPrompt' => ['nullable', 'string'],
            'isRequired' => ['sometimes', 'boolean'],
        ];
    }
}
