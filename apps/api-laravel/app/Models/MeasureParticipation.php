<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasureParticipation extends Model
{
    use HasFactory;

    public const VERIFICATION_TYPE_SELF_REPORTED = 'SELF_REPORTED';

    protected $fillable = [
        'measure_id',
        'user_id',
        'company_id',
        'team_id',
        'participated_at',
        'verification_type',
        'verified_at',
        'verified_by_user_id',
    ];

    protected $casts = [
        'participated_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function measure(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
