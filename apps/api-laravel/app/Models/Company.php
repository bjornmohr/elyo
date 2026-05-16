<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'status', 'anonymity_threshold', 'created_by_elyo_admin_id',
    ];

    protected $casts = [
        'anonymity_threshold' => 'integer',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(Survey::class);
    }

    public function inviteTokens(): HasMany
    {
        return $this->hasMany(InviteToken::class);
    }

    public function measures(): HasMany
    {
        return $this->hasMany(Measure::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_elyo_admin_id');
    }
}
