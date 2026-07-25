<?php

namespace App\Models\Health;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A wearable provider connection, keyed on `health_subject_id` (ADR-003 D8).
 * Dormant feature: no route reaches it (prompt 08a report).
 *
 * There is deliberately no `user()` relation: the identity link exists only
 * inside the mapping domain.
 */
class WearableConnection extends Model
{
    use HasFactory;
    use HasUlids;

    protected $connection = 'health';

    protected $table = 'wearable_connections';

    protected $fillable = [
        'health_subject_id', 'source', 'access_token', 'refresh_token',
        'expires_at', 'is_active', 'connected_at',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'connected_at' => 'datetime',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(HealthSubject::class, 'health_subject_id');
    }
}
