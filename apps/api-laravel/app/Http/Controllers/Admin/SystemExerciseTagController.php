<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SystemExerciseTagResource;
use App\Models\SystemExerciseTag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SystemExerciseTagController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'category' => ['sometimes', Rule::in([
                SystemExerciseTag::CATEGORY_BODY_REGION,
                SystemExerciseTag::CATEGORY_GOAL,
                SystemExerciseTag::CATEGORY_SETTING,
                SystemExerciseTag::CATEGORY_EQUIPMENT,
                SystemExerciseTag::CATEGORY_CONTRAINDICATION,
                SystemExerciseTag::CATEGORY_PERSONA_HINT,
                SystemExerciseTag::CATEGORY_HEALTH_FOCUS,
            ])],
            'isActive' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'nullable', 'string'],
        ]);

        $query = SystemExerciseTag::query()
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('label');

        if (isset($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        if ($request->has('isActive')) {
            $query->where('is_active', $request->boolean('isActive'));
        }

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(key) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(label) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$term]);
            });
        }

        return SystemExerciseTagResource::collection($query->get());
    }
}
