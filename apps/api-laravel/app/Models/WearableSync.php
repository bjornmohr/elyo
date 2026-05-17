<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WearableSync extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'user_id', 'source', 'date', 'steps', 'heart_rate',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
