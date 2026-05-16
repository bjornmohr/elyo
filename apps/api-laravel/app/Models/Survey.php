<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Enums\SurveyStatus;

class Survey extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'status', 'starts_at',
        'ends_at', 'is_anonymous', 'company_id'
    ];

    protected $casts = [
        'status' => SurveyStatus::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_anonymous' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'survey_team');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }
}
