<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SystemExerciseTag extends Model
{
    use HasFactory;

    public const CATEGORY_BODY_REGION = 'BODY_REGION';
    public const CATEGORY_GOAL = 'GOAL';
    public const CATEGORY_SETTING = 'SETTING';
    public const CATEGORY_EQUIPMENT = 'EQUIPMENT';
    public const CATEGORY_CONTRAINDICATION = 'CONTRAINDICATION';
    public const CATEGORY_PERSONA_HINT = 'PERSONA_HINT';
    public const CATEGORY_HEALTH_FOCUS = 'HEALTH_FOCUS';

    protected $fillable = [
        'category', 'key', 'label', 'description',
        'sort_order', 'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function exercises(): BelongsToMany
    {
        return $this->belongsToMany(SystemExercise::class, 'system_exercise_tag');
    }
}
