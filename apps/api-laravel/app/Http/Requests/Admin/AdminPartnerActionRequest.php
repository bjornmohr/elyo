<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

class AdminPartnerActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole(Role::ELYO_ADMIN);
    }

    public function rules(): array
    {
        return [
            'action' => 'required|string|in:approve,reject,suspend,unsuspend',
            'rejectionReason' => 'required_if:action,reject|string|nullable',
        ];
    }
}
