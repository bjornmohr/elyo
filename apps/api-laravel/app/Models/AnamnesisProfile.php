<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnamnesisProfile extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'user_id', 'completion_pct', 'birth_year', 'biological_sex',
        'activity_level', 'sleep_quality', 'stress_tendency',
        'smoking_status', 'nutrition_type', 'chronic_patterns', 'has_medication',
    ];

    protected $casts = [
        'completion_pct' => 'integer',
        'birth_year' => 'integer',
        'chronic_patterns' => 'array', // mapping from jsonb
        'has_medication' => 'boolean',
        // Sensible fields should ideally be encrypted
        'biological_sex' => 'encrypted',
        'activity_level' => 'encrypted',
        'sleep_quality' => 'encrypted',
        'stress_tendency' => 'encrypted',
        'smoking_status' => 'encrypted',
        'nutrition_type' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
