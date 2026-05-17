<?php

namespace App\Http\Requests\Company;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole([Role::COMPANY_ADMIN, Role::COMPANY_OWNER, Role::COMPANY_MANAGER]);
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|min:3|max:100',
            'category' => 'required|string|in:workshop,flexibility,sport,mental,nutrition',
            'description' => 'required|string|min:10|max:500',
            'teamId' => [
                'nullable',
                'integer',
                Rule::exists('teams', 'id')->where(fn ($query) => $query->where('company_id', $this->user()->company_id)),
            ],
            'status' => 'nullable|string|in:SUGGESTED,ACTIVE',
        ];
    }
}
