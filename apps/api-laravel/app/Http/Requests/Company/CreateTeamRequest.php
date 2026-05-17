<?php

namespace App\Http\Requests\Company;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole([Role::COMPANY_ADMIN, Role::COMPANY_OWNER]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:100',
            'description' => 'nullable|string|max:500',
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'managerId' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('company_id', $this->user()->company_id)
                    ->where('status', 'active')
                ),
            ],
        ];
    }
}
