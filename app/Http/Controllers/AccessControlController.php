<?php

namespace App\Http\Controllers;

use App\Support\AccessControlProvisioner;
use App\Support\AuditLogger;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccessControlController extends Controller
{
    public function index(CompanyContext $context, AccessControlProvisioner $provisioner): View
    {
        $company = $context->current();
        $provisioner->syncCompany($company);

        $modules = DB::table('modules')
            ->join('company_modules', function ($join) use ($company): void {
                $join->on('company_modules.module_id', '=', 'modules.id')
                    ->where('company_modules.company_id', $company->id);
            })
            ->select('modules.*', 'company_modules.is_licensed', 'company_modules.is_enabled', 'company_modules.licensed_until')
            ->orderBy('modules.sort_order')
            ->get();

        $permissions = DB::table('permissions')
            ->join('modules', 'modules.id', '=', 'permissions.module_id')
            ->join('company_modules', function ($join) use ($company): void {
                $join->on('company_modules.module_id', '=', 'modules.id')
                    ->where('company_modules.company_id', $company->id)
                    ->where('company_modules.is_enabled', true);
            })
            ->where('modules.status', 'active')
            ->orderBy('modules.sort_order')
            ->orderBy('permissions.sort_order')
            ->select('permissions.*', 'modules.key as module_key', 'modules.name as module_name')
            ->get();
        $visiblePermissionIds = $permissions->pluck('id')->map(fn ($id) => (int) $id);

        $roles = DB::table('roles')
            ->where('company_id', $company->id)
            ->orderByRaw("CASE `key` WHEN 'super_admin' THEN 1 WHEN 'purchasing' THEN 2 WHEN 'warehouse' THEN 3 WHEN 'finance' THEN 4 ELSE 5 END")
            ->get()
            ->map(function (object $role) use ($visiblePermissionIds): object {
                $role->permission_ids = DB::table('role_permissions')
                    ->where('role_id', $role->id)
                    ->whereIn('permission_id', $visiblePermissionIds)
                    ->pluck('permission_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                return $role;
            });

        $summary = [
            'enabled_modules' => $modules->where('is_enabled', true)->count(),
            'total_modules' => $modules->count(),
            'permissions' => $permissions->count(),
            'roles' => $roles->count(),
            'users' => DB::table('company_user')->where('company_id', $company->id)->where('is_active', true)->count(),
        ];

        return view('access_control.index', [
            'company' => $company,
            'modules' => $modules,
            'roles' => $roles,
            'permissionGroups' => $permissions->groupBy('module_key'),
            'summary' => $summary,
        ]);
    }

    public function updateModules(Request $request, CompanyContext $context, AccessControlProvisioner $provisioner): RedirectResponse
    {
        $company = $context->current();
        $provisioner->syncCompany($company);
        $validated = $request->validate([
            'modules' => ['nullable', 'array'],
            'modules.*' => ['integer', 'distinct', 'exists:modules,id'],
        ]);
        $requestedIds = collect($validated['modules'] ?? [])->map(fn ($id) => (int) $id)->unique();
        $plannedRequested = DB::table('modules')->whereIn('id', $requestedIds)->where('status', '!=', 'active')->exists();
        $unlicensedRequested = DB::table('company_modules')
            ->where('company_id', $company->id)
            ->whereIn('module_id', $requestedIds)
            ->where(function ($query): void {
                $query->where('is_licensed', false)
                    ->orWhere(function ($expiry): void {
                        $expiry->whereNotNull('licensed_until')->whereDate('licensed_until', '<', today());
                    });
            })
            ->exists();

        if ($plannedRequested) {
            throw ValidationException::withMessages(['modules' => __('access.errors.planned_module')]);
        }

        if ($unlicensedRequested) {
            throw ValidationException::withMessages(['modules' => 'Modul belum termasuk dalam lisensi perusahaan atau masa lisensinya telah berakhir.']);
        }

        $modules = DB::table('modules')->orderBy('sort_order')->get();
        $old = DB::table('company_modules')
            ->join('modules', 'modules.id', '=', 'company_modules.module_id')
            ->where('company_modules.company_id', $company->id)
            ->pluck('company_modules.is_enabled', 'modules.key')
            ->map(fn ($enabled) => (bool) $enabled)
            ->all();

        DB::transaction(function () use ($modules, $requestedIds, $company): void {
            foreach ($modules as $module) {
                $entitlement = DB::table('company_modules')
                    ->where('company_id', $company->id)
                    ->where('module_id', $module->id)
                    ->first();
                $licensed = $module->key === 'core' || (
                    $entitlement?->is_licensed
                    && (! $entitlement->licensed_until || $entitlement->licensed_until >= today()->toDateString())
                );
                $enabled = $module->key === 'core'
                    || ($licensed && $module->status === 'active' && $requestedIds->contains((int) $module->id));

                DB::table('company_modules')
                    ->where('company_id', $company->id)
                    ->where('module_id', $module->id)
                    ->update(['is_enabled' => $enabled, 'updated_at' => now()]);

            }
        });

        $provisioner->syncCompany($company);

        $new = DB::table('company_modules')
            ->join('modules', 'modules.id', '=', 'company_modules.module_id')
            ->where('company_modules.company_id', $company->id)
            ->pluck('company_modules.is_enabled', 'modules.key')
            ->map(fn ($enabled) => (bool) $enabled)
            ->all();

        AuditLogger::log('company_modules_updated', 'company', (int) $company->id, $old, $new, (int) $company->id);

        return redirect()->route('access-control.index')->with('status', __('access.feedback.modules_saved'));
    }

    public function updateRolePermissions(Request $request, int $role, CompanyContext $context, AccessControlProvisioner $provisioner): RedirectResponse
    {
        $company = $context->current();
        $provisioner->syncCompany($company);
        $roleRow = DB::table('roles')->where('company_id', $company->id)->where('id', $role)->first();
        abort_unless($roleRow, 404);

        if ($roleRow->key === 'super_admin') {
            throw ValidationException::withMessages(['permissions' => __('access.errors.protected_role')]);
        }

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ]);
        $permissionIds = collect($validated['permissions'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $requiredPermissionIds = DB::table('permissions')
            ->whereIn('key', ['core.dashboard.view'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
        $permissionIds = $permissionIds->merge($requiredPermissionIds)->unique()->values();
        $allowedPermissionIds = DB::table('permissions')
            ->join('company_modules', function ($join) use ($company): void {
                $join->on('company_modules.module_id', '=', 'permissions.module_id')
                    ->where('company_modules.company_id', $company->id)
                    ->where('company_modules.is_enabled', true);
            })
            ->pluck('permissions.id')
            ->map(fn ($id) => (int) $id);

        if ($permissionIds->diff($allowedPermissionIds)->isNotEmpty()) {
            throw ValidationException::withMessages(['permissions' => __('access.errors.permission_outside_modules')]);
        }

        $old = DB::table('role_permissions')
            ->where('role_id', $roleRow->id)
            ->whereIn('permission_id', $allowedPermissionIds)
            ->pluck('permission_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        DB::transaction(function () use ($permissionIds, $allowedPermissionIds, $roleRow): void {
            DB::table('role_permissions')
                ->where('role_id', $roleRow->id)
                ->whereIn('permission_id', $allowedPermissionIds)
                ->delete();

            if ($permissionIds->isNotEmpty()) {
                DB::table('role_permissions')->insert($permissionIds->map(fn (int $permissionId) => [
                    'role_id' => $roleRow->id,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            }

            DB::table('roles')->where('id', $roleRow->id)->update(['is_customized' => true, 'updated_at' => now()]);
        });

        AuditLogger::log(
            'role_permissions_updated',
            'role',
            (int) $roleRow->id,
            ['permissions' => $old],
            ['permissions' => $permissionIds->all()],
            (int) $company->id,
        );

        return redirect()->route('access-control.index')->with('status', __('access.feedback.permissions_saved', ['role' => $roleRow->name]));
    }
}
