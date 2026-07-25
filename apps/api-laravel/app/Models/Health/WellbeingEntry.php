<?php

namespace App\Models\Health;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A daily wellbeing check-in, keyed on `health_subject_id` (ADR-003 D3).
 *
 * There is deliberately no `user()` / `company()` relation: the identity link
 * exists only inside the mapping domain and is reachable exclusively through
 * `App\Services\Privacy\MappingService`.
 */
class WellbeingEntry extends Model
{
    use HasFactory;
    use HasUlids;

    protected $connection = 'health';

    protected $table = 'wellbeing_entries';

    protected $fillable = [
        'health_subject_id', 'mood', 'stress', 'energy', 'score', 'period_key',
    ];

    protected $casts = [
        'score' => 'double',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(HealthSubject::class, 'health_subject_id');
    }
}
