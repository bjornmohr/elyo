<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class PatchMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->value === 'COMPANY_ADMIN';
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|in:ACTIVE,COMPLETED,DISMISSED',
        ];
    }
}
