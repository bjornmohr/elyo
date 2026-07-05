<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabMarker extends Model
{
    public const STATUS_BELOW_RANGE = 'unter Bereich';
    public const STATUS_IN_RANGE = 'im Orientierungsbereich';
    public const STATUS_ABOVE_RANGE = 'über Bereich';

    public const ALLOWED_STATUSES = [
        self::STATUS_BELOW_RANGE,
        self::STATUS_IN_RANGE,
        self::STATUS_ABOVE_RANGE,
    ];

    protected $fillable = [
        'user_id',
        'marker_key',
        'value',
        'status',
        'is_highlighted',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'is_highlighted' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
