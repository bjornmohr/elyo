<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class SurveyRespondRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.questionId' => ['required'],
            'answers.*.scaleValue' => ['nullable', 'integer', 'min:1', 'max:10'],
            'answers.*.textValue' => ['nullable', 'string', 'max:2000'],
            'answers.*.choiceValue' => ['nullable', 'string', 'max:200'],
            'answers.*.boolValue' => ['nullable', 'boolean'],
        ];
    }
}
