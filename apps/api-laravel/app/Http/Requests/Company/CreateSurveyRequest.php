<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class CreateSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role->value, ['COMPANY_ADMIN', 'COMPANY_MANAGER']);
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|min:3|max:120',
            'description' => 'nullable|string|max:500',
            'questions' => 'required|array|min:1|max:20',
            'questions.*.text' => 'required|string|min:3|max:300',
            'questions.*.type' => 'required|string|in:SCALE,MULTIPLE_CHOICE,TEXT,YES_NO',
            'questions.*.order' => 'required|integer|min:0',
            'questions.*.isRequired' => 'boolean',
            'questions.*.options' => 'nullable|array',
            'questions.*.scaleMinLabel' => 'nullable|string',
            'questions.*.scaleMaxLabel' => 'nullable|string',
        ];
    }
}
