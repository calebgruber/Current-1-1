<?php

namespace App\Models;

use App\Domain\Authentication\Models\Role;
use App\Support\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;

class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable, PasskeyAuthenticatable, SoftDeletes;

    protected $fillable = [
        'name', 'username', 'email', 'password', 'is_active', 'preferences',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    /** @var list<string> */
    protected array $auditExclude = ['last_login_at', 'last_login_ip', 'failed_login_attempts', 'locked_until'];

    /** @var array<int, string>|null */
    private ?array $permissionKeyCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'preferences' => 'array',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /** @return array<int, string> */
    public function permissionKeys(): array
    {
        if ($this->permissionKeyCache === null) {
            $this->permissionKeyCache = $this->roles()
                ->join('permission_role', 'permission_role.role_id', '=', 'roles.id')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->distinct()
                ->pluck('permissions.key')
                ->all();
        }

        return $this->permissionKeyCache;
    }

    public function hasPermission(string $key): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return in_array($key, $this->permissionKeys(), true);
    }

    public function flushPermissionCache(): void
    {
        $this->permissionKeyCache = null;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
