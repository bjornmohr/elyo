<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SystemMeasureTemplate extends Model
{
    use HasFactory;

    public const CATEGORY_MOBILITY = 'MOBILITY';
    public const CATEGORY_STRENGTH = 'STRENGTH';
    public const CATEGORY_BREATHING = 'BREATHING';
    public const CATEGORY_MINDFULNESS = 'MINDFULNESS';
    public const CATEGORY_EDUCATION = 'EDUCATION';
    public const CATEGORY_REFLECTION = 'REFLECTION';
    public const CATEGORY_MIXED = 'MIXED';

    public const DIFFICULTY_BEGINNER = 'BEGINNER';
    public const DIFFICULTY_INTERMEDIATE = 'INTERMEDIATE';
    public const DIFFICULTY_ADVANCED = 'ADVANCED';

    public const FREQUENCY_DAILY = 'DAILY';
    public const FREQUENCY_WEEKLY = 'WEEKLY';
    public const FREQUENCY_ON_DEMAND = 'ON_DEMAND';

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    protected $fillable = [
        'slug', 'title', 'short_description', 'description', 'goal_summary',
        'category', 'difficulty', 'estimated_duration_minutes', 'recommended_frequency',
        'default_points', 'streak_enabled', 'requires_feedback', 'is_featured',
        'target_signal', 'assignment_reason_template', 'effect_metric', 'effect_metric_unit',
        'location_tags', 'posture_tags', 'requires_floor',
        'status', 'created_by_user_id',
    ];

    protected $casts = [
        'estimated_duration_minutes' => 'integer',
        'default_points' => 'integer',
        'streak_enabled' => 'boolean',
        'requires_feedback' => 'boolean',
        'is_featured' => 'boolean',
        'location_tags' => 'array',
        'posture_tags' => 'array',
        'requires_floor' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (SystemMeasureTemplate $template): void {
            $template->slug ??= Str::slug($template->title);
            $template->category ??= self::CATEGORY_MIXED;
            $template->difficulty ??= self::DIFFICULTY_BEGINNER;
            $template->status ??= self::STATUS_ACTIVE;
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function templateExercises(): HasMany
    {
        return $this->hasMany(SystemMeasureTemplateExercise::class)->orderBy('position');
    }

    public function userSystemMeasures(): HasMany
    {
        return $this->hasMany(UserSystemMeasure::class, 'source_system_measure_template_id');
    }
}
