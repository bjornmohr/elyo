<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemMeasureTemplateExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sortOrder' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'customTitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'customInstructions' => ['sometimes', 'nullable', 'string'],
            'customDurationMinutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'customSets' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'customRepetitions' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'customHoldSeconds' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'customFeedbackPrompt' => ['sometimes', 'nullable', 'string'],
            'isRequired' => ['sometimes', 'boolean'],
        ];
    }
}
