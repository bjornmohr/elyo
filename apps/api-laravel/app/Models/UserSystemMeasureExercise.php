<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserSystemMeasureExercise extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_SKIPPED = 'SKIPPED';

    protected $fillable = [
        'user_system_measure_id', 'source_system_exercise_id',
        'position', 'title', 'short_description', 'description',
        'exercise_type', 'difficulty',
        'duration_minutes', 'sets', 'repetitions', 'hold_seconds',
        'instructions', 'safety_notes', 'contraindications',
        'feedback_prompt', 'requires_feedback', 'tag_snapshot',
        'status',
    ];

    protected $casts = [
        'position' => 'integer',
        'duration_minutes' => 'integer',
        'sets' => 'integer',
        'repetitions' => 'integer',
        'hold_seconds' => 'integer',
        'requires_feedback' => 'boolean',
        'tag_snapshot' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (UserSystemMeasureExercise $exercise): void {
            $exercise->status ??= self::STATUS_PENDING;
        });
    }

    public function userSystemMeasure(): BelongsTo
    {
        return $this->belongsTo(UserSystemMeasure::class);
    }

    public function sourceExercise(): BelongsTo
    {
        return $this->belongsTo(SystemExercise::class, 'source_system_exercise_id');
    }

    public function completions(): HasMany
    {
        return $this->hasMany(UserSystemMeasureExerciseCompletion::class);
    }
}
