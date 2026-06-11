<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSystemMeasureExerciseCompletion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_system_measure_id', 'user_system_measure_exercise_id',
        'user_id', 'company_id',
        'completed_at', 'period_key',
        'feedback_text', 'effort_rating', 'difficulty_rating',
        'pain_before_rating', 'pain_after_rating',
        'stress_before_rating', 'stress_after_rating',
        'points_awarded', 'points_transaction_id',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'effort_rating' => 'integer',
        'difficulty_rating' => 'integer',
        'pain_before_rating' => 'integer',
        'pain_after_rating' => 'integer',
        'stress_before_rating' => 'integer',
        'stress_after_rating' => 'integer',
        'points_awarded' => 'integer',
    ];

    public function userSystemMeasure(): BelongsTo
    {
        return $this->belongsTo(UserSystemMeasure::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(UserSystemMeasureExercise::class, 'user_system_measure_exercise_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function pointsTransaction(): BelongsTo
    {
        return $this->belongsTo(PointTransaction::class);
    }
}
