<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemMeasureTemplateExercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'system_measure_template_id', 'system_exercise_id',
        'position', 'is_required',
        'custom_title', 'custom_instructions',
        'custom_duration_minutes', 'custom_sets',
        'custom_repetitions', 'custom_hold_seconds',
        'custom_feedback_prompt',
    ];

    protected $casts = [
        'position' => 'integer',
        'is_required' => 'boolean',
        'custom_duration_minutes' => 'integer',
        'custom_sets' => 'integer',
        'custom_repetitions' => 'integer',
        'custom_hold_seconds' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(SystemMeasureTemplate::class, 'system_measure_template_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(SystemExercise::class, 'system_exercise_id');
    }
}
