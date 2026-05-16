<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected array $pendingRoles = [];

    protected $fillable = [
        'name', 'email', 'password', 'company_id', 'status','team_id', 'last_login_at',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    // Relationships

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function managedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'manager_id');
    }

    public function wellbeingEntries(): HasMany
    {
        return $this->hasMany(WellbeingEntry::class);
    }

    public function surveyResponses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function anamnesisProfile(): HasOne
    {
        return $this->hasOne(AnamnesisProfile::class);
    }

    public function healthDocuments(): HasMany
    {
        return $this->hasMany(HealthDocument::class);
    }

    public function userPoints(): HasOne
    {
        return $this->hasOne(UserPoints::class);
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }

    public function wearableConnections(): HasMany
    {
        return $this->hasMany(WearableConnection::class);
    }

    public function wearableSyncs(): HasMany
    {
        return $this->hasMany(WearableSync::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(UserDocument::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    // Role helpers

    protected static function booted(): void
    {
        static::created(function (User $user) {
            foreach ($user->pendingRoles as $role) {
                UserRole::firstOrCreate([
                    'user_id' => $user->id,
                    'role' => $role,
                ]);
            }
        });
    }

    public function setRoleAttribute(Role|string|null $role): void
    {
        if ($role !== null) {
            $this->pendingRoles[] = $role instanceof Role ? $role->value : Role::from($role)->value;
        }

        unset($this->attributes['role']);
    }

    public function hasRole(Role|string $role): bool
    {
        $value = $role instanceof Role ? $role->value : $role;
        return $this->roles->contains('role', Role::from($value));
    }

    public function hasAnyRole(array $roles): bool
    {
        $values = array_map(fn ($r) => $r instanceof Role ? $r : Role::from($r), $roles);
        return $this->roles->whereIn('role', $values)->isNotEmpty();
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isPlatformUser(): bool
    {
        return $this->hasAnyRole([Role::ELYO_ADMIN, Role::ELYO_SUPPORT]);
    }

    public function isCompanyUser(): bool
    {
        return $this->hasAnyRole(Role::companyPortalRoles());
    }

    public function isEmployee(): bool
    {
        return $this->hasRole(Role::EMPLOYEE);
    }

    public function canUsePortal(string $portal): bool
    {
        return match ($portal) {
            'admin' => $this->hasAnyRole(Role::adminPortalRoles()),
            'company' => $this->isCompanyUser(),
            'employee' => $this->isEmployee(),
            'partner' => $this->hasRole(Role::PARTNER),
            default => false,
        };
    }

    public function allowedPortals(): array
    {
        $portals = [];
        if ($this->hasAnyRole(Role::adminPortalRoles())) $portals[] = 'admin';
        if ($this->isCompanyUser()) $portals[] = 'company';
        if ($this->isEmployee()) $portals[] = 'employee';
        if ($this->hasRole(Role::PARTNER)) $portals[] = 'partner';
        return $portals;
    }

    public function roleNames(): array
    {
        return $this->roles->pluck('role')->map(fn ($r) => $r->value)->toArray();
    }
}
