<?php

namespace App\Models\Health;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catalogue metadata for a health document, keyed on `health_subject_id`
 * (ADR-003 D8). Dormant: nothing writes this model today (prompt 08a report).
 *
 * There is deliberately no `user()` relation: the identity link exists only
 * inside the mapping domain.
 */
class HealthDocument extends Model
{
    use HasFactory;
    use HasUlids;

    protected $connection = 'health';

    protected $table = 'health_documents';

    protected $fillable = [
        'health_subject_id', 'type', 'file_name', 'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(HealthSubject::class, 'health_subject_id');
    }
}
