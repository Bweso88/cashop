<?php

namespace App\Models;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\UserDevice;
use App\Domain\Identity\Models\UserProfile;
use App\Domain\Kyc\Models\KycProfile;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\RoutesNotifications;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Utilisateur Cashop (client ou employé). Identifiant UUID v7.
 *
 * RoutesNotifications plutôt que Notifiable : la table `notifications` de Cashop a son propre
 * schéma (docs/DATABASE.md) et n'est pas celle du canal "database" de Laravel.
 */
#[Fillable(['email', 'phone_e164', 'password', 'status'])]
#[Hidden(['password', 'transaction_pin_hash', 'mfa_secret', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuids, RoutesNotifications;

    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'mfa_secret' => 'encrypted',
            'mfa_enabled_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function kycProfile(): HasOne
    {
        return $this->hasOne(KycProfile::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(config('cashop.staff_roles'));
    }

    public function hasMfaEnabled(): bool
    {
        return $this->mfa_enabled_at !== null && $this->mfa_secret !== null;
    }

    public function hasPin(): bool
    {
        return $this->transaction_pin_hash !== null;
    }

    public function isLocked(): bool
    {
        return $this->status !== UserStatus::Active
            || ($this->locked_until !== null && $this->locked_until->isFuture());
    }
}
