<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Measure extends Model
{
    use HasFactory;

    public const VERIFICATION_REQUIREMENT_SELF_REPORT = 'SELF_REPORT';
    public const VERIFICATION_REQUIREMENT_QR_CODE = 'QR_CODE';

    protected $fillable = [
        'company_id', 'team_id', 'title', 'category',
        'description', 'status', 'suggested_at', 'started_at',
        'completed_at', 'created_by', 'measure_origin',
        'delivery_type', 'execution_type', 'verification_requirement',
        'visibility_scope', 'starts_at', 'ends_at', 'duration_minutes',
        'instructions', 'location_name', 'location_address', 'capacity',
        'points_override',
    ];

    protected $casts = [
        'suggested_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'duration_minutes' => 'integer',
        'capacity' => 'integer',
        'points_override' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Measure $measure): void {
            $measure->measure_origin ??= 'COMPANY_CREATED';
            $measure->delivery_type ??= 'ONSITE';
            $measure->execution_type ??= 'EVENT_PARTICIPATION';
            $measure->verification_requirement ??= self::VERIFICATION_REQUIREMENT_SELF_REPORT;
            $measure->visibility_scope = $measure->team_id === null ? 'COMPANY' : 'TEAM';
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function participations(): HasMany
    {
        return $this->hasMany(MeasureParticipation::class);
    }

    public function checkinTokens(): HasMany
    {
        return $this->hasMany(MeasureCheckinToken::class);
    }
}
