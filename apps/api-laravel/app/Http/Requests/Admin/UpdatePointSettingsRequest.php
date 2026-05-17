<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePointSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'daily_checkin' => ['required', 'integer', 'min:0', 'max:100000'],
            'streak_7days' => ['required', 'integer', 'min:0', 'max:100000'],
            'streak_30days' => ['required', 'integer', 'min:0', 'max:100000'],
            'anamnesis_completed' => ['required', 'integer', 'min:0', 'max:100000'],
            'medical_document_upload' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
