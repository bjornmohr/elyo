<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'birthYear' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'biologicalSex' => ['nullable', 'string', 'in:MALE,FEMALE,OTHER,PREFER_NOT_TO_SAY'],
            'activityLevel' => ['nullable', 'string', 'max:80'],
            'sleepQuality' => ['nullable', 'string', 'max:80'],
            'stressTendency' => ['nullable', 'string', 'max:80'],
            'smokingStatus' => ['nullable', 'string', 'max:80'],
            'nutritionType' => ['nullable', 'string', 'max:80'],
            'chronicPatterns' => ['nullable', 'array'],
            'chronicPatterns.*' => ['string', 'max:120'],
            'hasMedication' => ['nullable', 'boolean'],
        ];
    }
}
