<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class CheckinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mood' => ['required', 'integer', 'min:1', 'max:10'],
            'stress' => ['required', 'integer', 'min:1', 'max:10'],
            'energy' => ['required', 'integer', 'min:1', 'max:10'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
