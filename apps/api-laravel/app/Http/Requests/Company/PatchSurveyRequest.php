<?php

namespace App\Http\Requests\Company;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PatchSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole([Role::COMPANY_ADMIN, Role::COMPANY_MANAGER]);
    }

    public function rules(): array
    {
        return [
            'title' => 'nullable|string|min:3|max:120',
            'description' => 'nullable|string|max:500',
            'startsAt' => 'nullable|date',
            'endsAt' => 'nullable|date|after_or_equal:startsAt',
            'isAnonymous' => 'nullable|boolean',
            'teamIds' => 'nullable|array',
            'teamIds.*' => [
                'integer',
                Rule::exists('teams', 'id')->where(fn ($query) => $query->where('company_id', $this->user()->company_id)),
            ],
            'questions' => 'nullable|array|min:1|max:20',
            'questions.*.id' => 'nullable|integer',
            'questions.*.text' => 'required_with:questions|string|min:3|max:300',
            'questions.*.type' => 'required_with:questions|string|in:SCALE,MULTIPLE_CHOICE,TEXT,YES_NO',
            'questions.*.order' => 'required_with:questions|integer|min:0',
            'questions.*.isRequired' => 'boolean',
            'questions.*.options' => 'nullable|array',
            'questions.*.options.*' => 'required_with:questions.*.options|string|min:1|max:120',
            'questions.*.scaleMinLabel' => 'nullable|string|max:80',
            'questions.*.scaleMaxLabel' => 'nullable|string|max:80',
        ];
    }
}
