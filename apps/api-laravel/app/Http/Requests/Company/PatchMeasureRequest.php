<?php

namespace App\Http\Requests\Company;

use App\Enums\Role;
use App\Models\Measure;
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

    public function withValidator($validator): void
    {
        $validator->sometimes('endsAt', 'after_or_equal:startsAt', function (): bool {
            return $this->filled('startsAt') && $this->filled('endsAt');
        });

        $validator->after(function ($validator): void {
            if ($validator->errors()->has('startsAt') || $validator->errors()->has('endsAt')) {
                return;
            }

            $measure = Measure::where('id', $this->route('id'))
                ->where('company_id', $this->user()->company_id)
                ->first();

            if (! $measure) {
                return;
            }

            $startsAt = $this->has('startsAt')
                ? ($this->filled('startsAt') ? $this->date('startsAt') : null)
                : $measure->starts_at;
            $endsAt = $this->has('endsAt')
                ? ($this->filled('endsAt') ? $this->date('endsAt') : null)
                : $measure->ends_at;

            if ($startsAt && $endsAt && $endsAt->lt($startsAt)) {
                $validator->errors()->add('endsAt', __('validation.after_or_equal', [
                    'attribute' => 'ends at',
                    'date' => 'starts at',
                ]));
            }
        });
    }
}
