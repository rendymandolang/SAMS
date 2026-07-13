<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccessControlProvisioner
{
    public function syncAllCompanies(): void
    {
        if (! $this->tablesReady()) {
            return;
        }

        $this->syncCatalog();

        DB::table('companies')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (int $companyId) => $this->syncCompany($companyId, false));
    }

    public function syncCompany(int|object $company, bool $syncCatalog = true): void
    {
        if (! $this->tablesReady()) {
            return;
        }

        if ($syncCatalog) {
            $this->syncCatalog();
        }

        $companyId = is_object($company) ? (int) $company->id : $company;

        DB::transaction(function () use ($companyId): void {
            $now = now();
            $modules = DB::table('modules')->get()->keyBy('key');

            foreach (ModuleCatalog::modules() as $key => $definition) {
                $module = $modules->get($key);

                $entitlement = [
                    'company_id' => $companyId,
                    'module_id' => $module->id,
                    'is_enabled' => Schema::hasColumn('company_modules', 'is_licensed')
                        ? $key === 'core'
                        : $definition['status'] === 'active',
                    'settings' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (Schema::hasColumn('company_modules', 'is_licensed')) {
                    $entitlement['is_licensed'] = $key === 'core';
                }

                DB::table('company_modules')->insertOrIgnore($entitlement);

                if ($key === 'core' || $definition['status'] !== 'active') {
                    DB::table('company_modules')
                        ->where('company_id', $companyId)
                        ->where('module_id', $module->id)
                        ->update([
                            'is_enabled' => $key === 'core',
                            'updated_at' => $now,
                        ]);
                }
            }

            $enabledModuleIds = DB::table('company_modules')
                ->where('company_id', $companyId)
                ->where('is_enabled', true)
                ->pluck('module_id');
            $permissionIds = DB::table('permissions')
                ->whereIn('module_id', $enabledModuleIds)
                ->pluck('id', 'key');

            foreach (ModuleCatalog::roles() as $key => $definition) {
                $role = DB::table('roles')
                    ->where('company_id', $companyId)
                    ->where('key', $key)
                    ->first();

                if (! $role) {
                    $roleId = DB::table('roles')->insertGetId([
                        'company_id' => $companyId,
                        'key' => $key,
                        'name' => $definition['name'],
                        'description' => $definition['description'],
                        'is_system' => true,
                        'is_customized' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $customized = false;
                } else {
                    $roleId = (int) $role->id;
                    $customized = (bool) $role->is_customized;
                    DB::table('roles')->where('id', $roleId)->update([
                        'name' => $definition['name'],
                        'description' => $definition['description'],
                        'updated_at' => $now,
                    ]);
                }

                if ($key === 'super_admin' || ! $customized) {
                    $keys = ModuleCatalog::legacyRolePermissions()[$key] ?? [];
                    $ids = collect($keys)->map(fn (string $permission) => $permissionIds->get($permission))->filter()->values();

                    DB::table('role_permissions')->where('role_id', $roleId)->delete();
                    if ($ids->isNotEmpty()) {
                        DB::table('role_permissions')->insert($ids->map(fn (int $permissionId) => [
                            'role_id' => $roleId,
                            'permission_id' => $permissionId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])->all());
                    }
                }
            }

            $roleIds = DB::table('roles')->where('company_id', $companyId)->pluck('id', 'key');
            $memberships = DB::table('company_user')
                ->join('users', 'users.id', '=', 'company_user.user_id')
                ->where('company_user.company_id', $companyId)
                ->where('company_user.is_active', true)
                ->select('users.id', 'users.role')
                ->get();

            foreach ($memberships as $membership) {
                $hasAssignment = DB::table('company_user_roles')
                    ->where('company_id', $companyId)
                    ->where('user_id', $membership->id)
                    ->exists();

                if ($hasAssignment) {
                    continue;
                }

                $roleKey = $roleIds->has($membership->role) ? $membership->role : 'staff';
                DB::table('company_user_roles')->insert([
                    'company_id' => $companyId,
                    'user_id' => $membership->id,
                    'role_id' => $roleIds->get($roleKey),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function syncCatalog(): void
    {
        $now = now();

        foreach (ModuleCatalog::modules() as $key => $definition) {
            DB::table('modules')->updateOrInsert(
                ['key' => $key],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'status' => $definition['status'],
                    'sort_order' => $definition['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $moduleIds = DB::table('modules')->pluck('id', 'key');
        foreach (ModuleCatalog::permissions() as $key => $definition) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $key],
                [
                    'module_id' => $moduleIds->get($definition['module']),
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'sort_order' => $definition['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    private function tablesReady(): bool
    {
        return Schema::hasTable('modules')
            && Schema::hasTable('permissions')
            && Schema::hasTable('roles')
            && Schema::hasTable('role_permissions')
            && Schema::hasTable('company_modules')
            && Schema::hasTable('company_user_roles')
            && Schema::hasTable('companies')
            && Schema::hasTable('company_user');
    }
}
