<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserSystemMeasure extends Model
{
    use HasFactory;

    public const STATUS_ASSIGNED = 'ASSIGNED';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    protected $fillable = [
        'user_id', 'company_id',
        'source_system_measure_template_id', 'assigned_by_user_id',
        'title', 'description', 'assignment_reason', 'recommendation_context',
        'status', 'starts_at', 'ends_at', 'assigned_at', 'completed_at',
        'streak_enabled', 'points_enabled', 'points_per_completion',
        'requires_feedback',
    ];

    protected $casts = [
        'recommendation_context' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
        'streak_enabled' => 'boolean',
        'points_enabled' => 'boolean',
        'points_per_completion' => 'integer',
        'requires_feedback' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (UserSystemMeasure $measure): void {
            $measure->status ??= self::STATUS_ASSIGNED;
            $measure->assigned_at ??= now();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function sourceTemplate(): BelongsTo
    {
        return $this->belongsTo(SystemMeasureTemplate::class, 'source_system_measure_template_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(UserSystemMeasureExercise::class)->orderBy('position');
    }

    public function completions(): HasMany
    {
        return $this->hasMany(UserSystemMeasureExerciseCompletion::class);
    }
}
