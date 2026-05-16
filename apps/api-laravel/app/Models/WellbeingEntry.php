<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WellbeingEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'mood', 'stress', 'energy', 'score', 'note',
        'period_key', 'company_id', 'user_id'
    ];

    protected $casts = [
        'score' => 'double',
        'note' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
