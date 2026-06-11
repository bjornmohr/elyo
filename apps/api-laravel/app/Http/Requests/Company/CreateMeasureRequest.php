<?php

namespace App\Http\Requests\Company;

use App\Enums\Role;
use App\Models\Measure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateMeasureRequest extends FormRequest
{
    public const DELIVERY_TYPES = ['REMOTE', 'ONSITE', 'HYBRID'];
    public const EXECUTION_TYPES = ['INFORMATION_ONLY', 'GUIDED_SESSION', 'SELF_REPORTED_ACTION', 'EVENT_PARTICIPATION', 'CHALLENGE'];
    public const VERIFICATION_REQUIREMENTS = [
        Measure::VERIFICATION_REQUIREMENT_SELF_REPORT,
        Measure::VERIFICATION_REQUIREMENT_QR_CODE,
    ];

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
            'deliveryType' => ['sometimes', 'string', Rule::in(self::DELIVERY_TYPES)],
            'executionType' => ['sometimes', 'string', Rule::in(self::EXECUTION_TYPES)],
            'verificationRequirement' => ['sometimes', 'string', Rule::in(self::VERIFICATION_REQUIREMENTS)],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date'],
            'durationMinutes' => ['nullable', 'integer', 'min:1'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'locationName' => ['nullable', 'string', 'max:255'],
            'locationAddress' => ['nullable', 'string', 'max:1000'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'pointsOverride' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->sometimes('endsAt', 'after:startsAt', function (): bool {
            return $this->filled('startsAt') && $this->filled('endsAt');
        });
    }
}
