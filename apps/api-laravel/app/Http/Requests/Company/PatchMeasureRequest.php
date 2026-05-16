<?php

namespace App\Http\Requests\Company;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

class PatchMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole([Role::COMPANY_ADMIN, Role::COMPANY_OWNER]);
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|in:ACTIVE,COMPLETED,DISMISSED',
        ];
    }
}
