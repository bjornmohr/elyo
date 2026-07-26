<?php

namespace App\Models\Health;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One timestamped lab reading, keyed only on `health_subject_id`.
 *
 * There is deliberately no identity-domain relation. Status is derived by
 * `LabMarkerService` from this value and the related catalog bounds.
 */
class LabMarkerReading extends Model
{
    use HasFactory;
    use HasUlids;

    protected $connection = 'health';

    protected $table = 'lab_marker_readings';

    protected $fillable = [
        'health_subject_id',
        'marker_key',
        'value',
        'measured_at',
        'source',
    ];

    protected $casts = [
        'value' => 'decimal:4',
        'measured_at' => 'date',
    ];

    public function marker(): BelongsTo
    {
        return $this->belongsTo(LabMarker::class, 'marker_key', 'marker_key');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(HealthSubject::class, 'health_subject_id');
    }
}
