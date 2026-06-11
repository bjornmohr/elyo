<?php

namespace App\Http\Requests\Company;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PatchMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole([Role::COMPANY_ADMIN, Role::COMPANY_OWNER, Role::COMPANY_MANAGER]);
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'min:3', 'max:100'],
            'category' => ['sometimes', 'required', 'string', 'in:workshop,flexibility,sport,mental,nutrition'],
            'description' => ['sometimes', 'required', 'string', 'min:10', 'max:500'],
            'status' => 'sometimes|required|string|in:ACTIVE,COMPLETED,DISMISSED',
            'deliveryType' => ['sometimes', 'string', Rule::in(CreateMeasureRequest::DELIVERY_TYPES)],
            'executionType' => ['sometimes', 'string', Rule::in(CreateMeasureRequest::EXECUTION_TYPES)],
            'verificationRequirement' => ['sometimes', 'string', Rule::in(CreateMeasureRequest::VERIFICATION_REQUIREMENTS)],
            'startsAt' => ['sometimes', 'nullable', 'date'],
            'endsAt' => ['sometimes', 'nullable', 'date'],
            'durationMinutes' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'locationName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'locationAddress' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'pointsOverride' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }


}
