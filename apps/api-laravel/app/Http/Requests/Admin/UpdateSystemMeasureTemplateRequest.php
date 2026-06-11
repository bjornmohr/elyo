<?php

namespace App\Http\Requests\Admin;

use App\Models\SystemMeasureTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSystemMeasureTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'shortDescription' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'category' => ['sometimes', Rule::in([
                SystemMeasureTemplate::CATEGORY_MOBILITY,
                SystemMeasureTemplate::CATEGORY_STRENGTH,
                SystemMeasureTemplate::CATEGORY_BREATHING,
                SystemMeasureTemplate::CATEGORY_MINDFULNESS,
                SystemMeasureTemplate::CATEGORY_EDUCATION,
                SystemMeasureTemplate::CATEGORY_REFLECTION,
                SystemMeasureTemplate::CATEGORY_MIXED,
            ])],
            'difficulty' => ['sometimes', Rule::in([
                SystemMeasureTemplate::DIFFICULTY_BEGINNER,
                SystemMeasureTemplate::DIFFICULTY_INTERMEDIATE,
                SystemMeasureTemplate::DIFFICULTY_ADVANCED,
            ])],
            'status' => ['sometimes', Rule::in([
                SystemMeasureTemplate::STATUS_DRAFT,
                SystemMeasureTemplate::STATUS_ACTIVE,
                SystemMeasureTemplate::STATUS_ARCHIVED,
            ])],
            'estimatedDurationMinutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'isFeatured' => ['sometimes', 'boolean'],
        ];
    }
}
