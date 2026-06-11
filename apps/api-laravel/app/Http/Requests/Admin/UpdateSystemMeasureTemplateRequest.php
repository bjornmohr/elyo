<?php

namespace App\Http\Requests\Admin;

class UpdateSystemMeasureTemplateRequest extends CreateSystemMeasureTemplateRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'shortDescription' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'category' => ['sometimes', $this->enumRule(self::categories())],
            'difficulty' => ['sometimes', $this->enumRule(self::difficulties())],
            'estimatedDurationMinutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'status' => ['sometimes', $this->enumRule(self::statuses())],
            'isFeatured' => ['sometimes', 'boolean'],
        ];
    }

    private function enumRule(array $values)
    {
        return \Illuminate\Validation\Rule::in($values);
    }
}
