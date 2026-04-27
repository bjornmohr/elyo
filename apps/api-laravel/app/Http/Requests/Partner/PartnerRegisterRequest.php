<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class PartnerRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|unique:partners,email',
            'password' => 'required|string|min:8',
            'name' => 'required|string',
            'type' => 'required|string',
            'categories' => 'required|array',
            'description' => 'nullable|string',
            'address' => 'required|string',
            'city' => 'required|string',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'website' => 'nullable|url',
            'phone' => 'nullable|string',
            'minimum_level' => 'required|integer|min:0',
            'nachweis_url' => 'required|url',
        ];
    }
}
