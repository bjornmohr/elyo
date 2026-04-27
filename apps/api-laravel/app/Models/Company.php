<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\CheckinFrequency;

class Company extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'name', 'slug', 'logo_url', 'primary_color', 'industry',
        'employee_range', 'country', 'checkin_frequency',
        'anonymity_threshold', 'billing_email'
    ];

    protected $casts = [
        'checkin_frequency' => CheckinFrequency::class,
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
}
