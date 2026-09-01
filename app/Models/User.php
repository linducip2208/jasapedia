<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[ObservedBy([\App\Observers\UserObserver::class])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'status', 'locale',
        'email_verified_at', 'phone_verified_at', 'two_factor_enabled_at',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'two_factor_enabled_at' => 'datetime',
            'locked_until' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role')
            ->withPivot('organization_id')
            ->withTimestamps();
    }

    public function hasRole(string $name, ?int $organizationId = null): bool
    {
        return $this->roles()
            ->when($organizationId !== null, fn ($q) => $q->wherePivot('organization_id', $organizationId))
            ->where('roles.name', $name)
            ->exists();
    }

    public function isStaff(): bool
    {
        return $this->roles()->where('roles.is_staff', true)->exists();
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->locked_until === null || $this->locked_until->isPast());
    }

    public function permissions(): array
    {
        return app(\App\Domain\Authorization\PermissionRegistrar::class)->userPermissions($this);
    }

    public function can($ability, $arguments = []): bool
    {
        if (app(\App\Domain\Authorization\PermissionRegistrar::class)->userHasPermission($this, $ability)) {
            return true;
        }

        return parent::can($ability, $arguments);
    }
}
