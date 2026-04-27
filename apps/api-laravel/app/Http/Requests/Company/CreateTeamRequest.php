<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class CreateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->value === 'COMPANY_ADMIN';
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:100',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:7',
        ];
    }
}
