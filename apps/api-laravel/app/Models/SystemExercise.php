<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SystemExercise extends Model
{
    use HasFactory;

    public const TYPE_MOBILITY = 'MOBILITY';
    public const TYPE_STRENGTH = 'STRENGTH';
    public const TYPE_BREATHING = 'BREATHING';
    public const TYPE_MINDFULNESS = 'MINDFULNESS';
    public const TYPE_EDUCATION = 'EDUCATION';
    public const TYPE_REFLECTION = 'REFLECTION';

    public const DIFFICULTY_BEGINNER = 'BEGINNER';
    public const DIFFICULTY_INTERMEDIATE = 'INTERMEDIATE';
    public const DIFFICULTY_ADVANCED = 'ADVANCED';

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    protected $fillable = [
        'slug', 'title', 'short_description', 'description',
        'exercise_type', 'difficulty',
        'default_duration_minutes', 'default_sets', 'default_repetitions', 'default_hold_seconds',
        'instructions', 'safety_notes', 'contraindications',
        'default_feedback_prompt', 'requires_feedback',
        'steps', 'main_pictogram_path', 'main_pictogram_alt',
        'location_tags', 'posture_tags', 'requires_floor', 'default_effort',
        'status', 'created_by_user_id',
    ];

    protected $casts = [
        'default_duration_minutes' => 'integer',
        'default_sets' => 'integer',
        'default_repetitions' => 'integer',
        'default_hold_seconds' => 'integer',
        'requires_feedback' => 'boolean',
        'steps' => 'array',
        'location_tags' => 'array',
        'posture_tags' => 'array',
        'requires_floor' => 'boolean',
        'default_effort' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (SystemExercise $exercise): void {
            $exercise->slug ??= Str::slug($exercise->title);
            $exercise->exercise_type ??= self::TYPE_MOBILITY;
            $exercise->difficulty ??= self::DIFFICULTY_BEGINNER;
            $exercise->status ??= self::STATUS_ACTIVE;
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(SystemExerciseTag::class, 'system_exercise_tag');
    }

    public function templateExercises(): HasMany
    {
        return $this->hasMany(SystemMeasureTemplateExercise::class);
    }
}
