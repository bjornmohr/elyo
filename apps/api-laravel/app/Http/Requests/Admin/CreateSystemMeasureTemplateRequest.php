<?php

namespace App\Http\Requests\Admin;

use App\Models\SystemMeasureTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSystemMeasureTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'shortDescription' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'category' => ['sometimes', Rule::in(self::categories())],
            'difficulty' => ['sometimes', Rule::in(self::difficulties())],
            'estimatedDurationMinutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'status' => ['sometimes', Rule::in(self::statuses())],
            'isFeatured' => ['sometimes', 'boolean'],
        ];
    }

    public static function categories(): array
    {
        return [
            SystemMeasureTemplate::CATEGORY_MOBILITY,
            SystemMeasureTemplate::CATEGORY_STRENGTH,
            SystemMeasureTemplate::CATEGORY_BREATHING,
            SystemMeasureTemplate::CATEGORY_MINDFULNESS,
            SystemMeasureTemplate::CATEGORY_EDUCATION,
            SystemMeasureTemplate::CATEGORY_REFLECTION,
            SystemMeasureTemplate::CATEGORY_MIXED,
        ];
    }

    public static function difficulties(): array
    {
        return [
            SystemMeasureTemplate::DIFFICULTY_BEGINNER,
            SystemMeasureTemplate::DIFFICULTY_INTERMEDIATE,
            SystemMeasureTemplate::DIFFICULTY_ADVANCED,
        ];
    }

    public static function statuses(): array
    {
        return [
            SystemMeasureTemplate::STATUS_DRAFT,
            SystemMeasureTemplate::STATUS_ACTIVE,
            SystemMeasureTemplate::STATUS_ARCHIVED,
        ];
    }
}
