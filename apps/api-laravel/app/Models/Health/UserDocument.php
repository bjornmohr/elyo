<?php

namespace App\Models\Health;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Metadata of a medical document uploaded by an employee, keyed on
 * `health_subject_id` (ADR-003 D8). The bytes live on the storage disk;
 * ADR-001 §2.9 storage hardening follow-up.
 *
 * There is deliberately no `user()` relation: the identity link exists only
 * inside the mapping domain and is reachable exclusively through
 * `App\Services\Privacy\MappingService`.
 */
class UserDocument extends Model
{
    use HasFactory;
    use HasUlids;

    protected $connection = 'health';

    protected $table = 'user_documents';

    protected $fillable = [
        'health_subject_id', 'file_name', 'blob_url', 'blob_key',
        'mime_type', 'size', 'uploaded_at',
    ];

    protected $casts = [
        'size' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(HealthSubject::class, 'health_subject_id');
    }
}
