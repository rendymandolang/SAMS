<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccessManager
{
    private array $moduleCache = [];

    private array $permissionCache = [];

    private array $roleCache = [];

    public function __construct(private readonly CompanyContext $companyContext) {}

    public function moduleEnabled(string $moduleKey, ?User $user = null): bool
    {
        $definition = ModuleCatalog::modules()[$moduleKey] ?? null;
        if (! $definition || $definition['status'] !== 'active') {
            return false;
        }

        if ($moduleKey === 'core') {
            return true;
        }

        if (! $this->tablesReady()) {
            return true;
        }

        $companyId = $this->companyContext->id();
        $cacheKey = $companyId.':'.$moduleKey;

        return $this->moduleCache[$cacheKey] ??= $this->subscriptionAllowsAccess($companyId)
            && DB::table('company_modules')
                ->join('modules', 'modules.id', '=', 'company_modules.module_id')
                ->where('company_modules.company_id', $companyId)
                ->where('modules.key', $moduleKey)
                ->where('modules.status', 'active')
                ->where('company_modules.is_licensed', true)
                ->where(function ($query): void {
                    $query->whereNull('company_modules.licensed_until')
                        ->orWhereDate('company_modules.licensed_until', '>=', today());
                })
                ->where('company_modules.is_enabled', true)
                ->exists();
    }

    public function allows(string $permissionKey, ?User $user = null): bool
    {
        $user ??= auth()->user();
        if (! $user || ! $user->is_active) {
            return false;
        }

        $permissionDefinition = ModuleCatalog::permissions()[$permissionKey] ?? null;
        if (! $permissionDefinition || ! $this->moduleEnabled($permissionDefinition['module'], $user)) {
            return false;
        }

        if (! $this->tablesReady()) {
            return in_array($permissionKey, ModuleCatalog::legacyRolePermissions()[$user->role] ?? [], true);
        }

        $companyId = $this->companyContext->id();
        $cacheKey = $companyId.':'.$user->id.':'.$permissionKey;

        if (array_key_exists($cacheKey, $this->permissionCache)) {
            return $this->permissionCache[$cacheKey];
        }

        $roleKeys = $this->roleKeys($user);
        if (in_array('super_admin', $roleKeys, true)) {
            return $this->permissionCache[$cacheKey] = true;
        }

        if ($roleKeys === []) {
            return $this->permissionCache[$cacheKey] = false;
        }

        return $this->permissionCache[$cacheKey] = DB::table('company_user_roles')
            ->join('roles', 'roles.id', '=', 'company_user_roles.role_id')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('company_user_roles.company_id', $companyId)
            ->where('company_user_roles.user_id', $user->id)
            ->where('roles.company_id', $companyId)
            ->where('permissions.key', $permissionKey)
            ->exists();
    }

    /** @return array<int, string> */
    public function roleKeys(?User $user = null): array
    {
        $user ??= auth()->user();
        if (! $user || ! $user->is_active) {
            return [];
        }

        if (! $this->tablesReady()) {
            return [$user->role];
        }

        $companyId = $this->companyContext->id();
        $cacheKey = $companyId.':'.$user->id;

        if (array_key_exists($cacheKey, $this->roleCache)) {
            return $this->roleCache[$cacheKey];
        }

        $roleKeys = DB::table('company_user_roles')
            ->join('roles', 'roles.id', '=', 'company_user_roles.role_id')
            ->where('company_user_roles.company_id', $companyId)
            ->where('company_user_roles.user_id', $user->id)
            ->where('roles.company_id', $companyId)
            ->orderByRaw("CASE roles.`key` WHEN 'super_admin' THEN 1 ELSE 2 END")
            ->orderBy('roles.name')
            ->pluck('roles.key')
            ->map(fn ($key): string => (string) $key)
            ->all();

        if ($roleKeys === [] && ! DB::table('roles')->where('company_id', $companyId)->exists()) {
            $roleKeys = [$user->role];
        }

        return $this->roleCache[$cacheKey] = $roleKeys;
    }

    public function primaryRoleKey(?User $user = null): ?string
    {
        return $this->roleKeys($user)[0] ?? null;
    }

    public function flush(): void
    {
        $this->moduleCache = [];
        $this->permissionCache = [];
        $this->roleCache = [];
    }

    private function tablesReady(): bool
    {
        return Schema::hasTable('company_modules')
            && Schema::hasTable('company_user_roles')
            && Schema::hasTable('roles')
            && Schema::hasTable('role_permissions')
            && Schema::hasTable('permissions');
    }

    private function subscriptionAllowsAccess(int $companyId): bool
    {
        if (! Schema::hasTable('company_subscriptions')) {
            return true;
        }

        $subscription = DB::table('company_subscriptions')->where('company_id', $companyId)->first();
        if (! $subscription || ! in_array($subscription->status, ['active', 'trial', 'grace'], true)) {
            return false;
        }

        if (! $subscription->expires_on || $subscription->expires_on >= today()->toDateString()) {
            return true;
        }

        return $subscription->grace_ends_on && $subscription->grace_ends_on >= today()->toDateString();
    }
}
