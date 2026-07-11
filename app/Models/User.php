<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\AccessManager;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function hasRole(string $role): bool
    {
        $roles = app(AccessManager::class)->roleKeys($this);

        return in_array('super_admin', $roles, true) || in_array($role, $roles, true);
    }

    public function hasAnyRole(array $roles): bool
    {
        $assignedRoles = app(AccessManager::class)->roleKeys($this);

        return in_array('super_admin', $assignedRoles, true) || array_intersect($assignedRoles, $roles) !== [];
    }

    public function hasPermission(string $permission): bool
    {
        return app(AccessManager::class)->allows($permission, $this);
    }

    public function canAccessModule(string $module): bool
    {
        return app(AccessManager::class)->moduleEnabled($module, $this);
    }

    public function currentRoleKey(): string
    {
        return app(AccessManager::class)->primaryRoleKey($this) ?? 'unassigned';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
