<?php

namespace App\Models\Health;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An employee's anamnesis, keyed on `health_subject_id` (ADR-003 D8).
 *
 * There is deliberately no `user()` / `company()` relation: the identity link
 * exists only inside the mapping domain and is reachable exclusively through
 * `App\Services\Privacy\MappingService`.
 */
class AnamnesisProfile extends Model
{
    use HasFactory;
    use HasUlids;

    protected $connection = 'health';

    protected $table = 'anamnesis_profiles';

    protected $fillable = [
        'health_subject_id', 'completion_pct', 'birth_year', 'biological_sex',
        'activity_level', 'sleep_quality', 'stress_tendency',
        'smoking_status', 'nutrition_type', 'chronic_patterns', 'has_medication',
    ];

    protected $casts = [
        'completion_pct' => 'integer',
        'birth_year' => 'integer',
        'chronic_patterns' => 'array', // mapping from jsonb
        'has_medication' => 'boolean',
        // Sensitive free-choice answers stay encrypted at rest.
        'biological_sex' => 'encrypted',
        'activity_level' => 'encrypted',
        'sleep_quality' => 'encrypted',
        'stress_tendency' => 'encrypted',
        'smoking_status' => 'encrypted',
        'nutrition_type' => 'encrypted',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(HealthSubject::class, 'health_subject_id');
    }
}
