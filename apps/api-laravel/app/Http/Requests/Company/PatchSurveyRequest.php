<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class PatchSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role->value, ['COMPANY_ADMIN', 'COMPANY_MANAGER']);
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|string|in:DRAFT,ACTIVE,CLOSED',
            'title' => 'nullable|string|min:3|max:120',
            'description' => 'nullable|string|max:500',
        ];
    }
}
