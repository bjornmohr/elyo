<?php

namespace App\Models\Health;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A wearable measurement snapshot, keyed on `health_subject_id` (ADR-003 D8).
 * Dormant feature: no route reaches it (prompt 08a report).
 *
 * There is deliberately no `user()` relation: the identity link exists only
 * inside the mapping domain.
 */
class WearableSync extends Model
{
    use HasFactory;
    use HasUlids;

    protected $connection = 'health';

    protected $table = 'wearable_syncs';

    protected $fillable = [
        'health_subject_id', 'source', 'date', 'steps', 'heart_rate',
        'sleep_hours', 'recovery_score', 'hrv', 'readiness', 'synced_at',
    ];

    protected $casts = [
        'date' => 'datetime',
        'steps' => 'integer',
        'heart_rate' => 'double',
        'sleep_hours' => 'double',
        'recovery_score' => 'double',
        'hrv' => 'double',
        'readiness' => 'double',
        'synced_at' => 'datetime',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(HealthSubject::class, 'health_subject_id');
    }
}
