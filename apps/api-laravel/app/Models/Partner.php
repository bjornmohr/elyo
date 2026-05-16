<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use App\Enums\PartnerVerificationStatus;

class Partner extends Authenticatable
{
    use HasFactory, HasApiTokens;

    protected $fillable = [
        'email', 'password_hash', 'name', 'type', 'categories',
        'description', 'address', 'city', 'lat', 'lng', 'website',
        'phone', 'minimum_level', 'nachweis_url', 'verification_status',
        'rejection_reason', 'reviewed_at', 'reviewed_by_id'
    ];

    protected $hidden = [
        'password_hash',
    ];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    protected $casts = [
        'categories' => 'array',
        'lat' => 'double',
        'lng' => 'double',
        'verification_status' => PartnerVerificationStatus::class,
        'reviewed_at' => 'datetime',
    ];
}
