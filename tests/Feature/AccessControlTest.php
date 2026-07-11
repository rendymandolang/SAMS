<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AccessControlProvisioner;
use App\Support\ModuleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_entitlements_roles_and_user_assignments_are_provisioned(): void
    {
        $this->seed();

        $company = DB::table('companies')->where('code', 'SAMS')->firstOrFail();

        $this->assertDatabaseCount('modules', count(ModuleCatalog::modules()));
        $this->assertDatabaseCount('permissions', count(ModuleCatalog::permissions()));
        $this->assertSame(
            count(ModuleCatalog::roles()),
            DB::table('roles')->where('company_id', $company->id)->count(),
        );
        $this->assertSame(
            DB::table('company_user')->where('company_id', $company->id)->where('is_active', true)->count(),
            DB::table('company_user_roles')->where('company_id', $company->id)->distinct()->count('user_id'),
        );

        foreach (ModuleCatalog::modules() as $key => $definition) {
            $entitlement = DB::table('company_modules')
                ->join('modules', 'modules.id', '=', 'company_modules.module_id')
                ->where('company_modules.company_id', $company->id)
                ->where('modules.key', $key)
                ->value('company_modules.is_enabled');

            $this->assertNotNull($entitlement, "Missing company entitlement for module [{$key}].");
            $this->assertSame($definition['status'] === 'active', (bool) $entitlement);
        }
    }

    public function test_only_super_admin_can_open_access_control_and_the_protected_role_cannot_be_changed(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $warehouse = User::query()->where('email', 'warehouse@sams.local')->firstOrFail();

        $this->actingAs($admin)
            ->get('/settings/access-control')
            ->assertOk()
            ->assertSee('Kontrol Akses')
            ->assertSee('Akuntansi')
            ->assertSee('Point of Sale');

        $this->actingAs($warehouse)
            ->get('/settings/access-control')
            ->assertForbidden();

        $superAdminRoleId = DB::table('roles')
            ->where('company_id', DB::table('companies')->where('code', 'SAMS')->value('id'))
            ->where('key', 'super_admin')
            ->value('id');

        $this->actingAs($admin)
            ->from('/settings/access-control')
            ->put("/settings/access-control/roles/{$superAdminRoleId}/permissions", [
                'permissions' => [],
            ])
            ->assertRedirect('/settings/access-control')
            ->assertSessionHasErrors('permissions');
    }

    public function test_disabling_a_module_blocks_its_routes_while_core_remains_available(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $company = DB::table('companies')->where('code', 'SAMS')->firstOrFail();
        $enabledModuleIds = DB::table('modules')
            ->where('status', 'active')
            ->whereNotIn('key', ['core', 'assets'])
            ->pluck('id')
            ->all();

        $this->actingAs($admin)
            ->put('/settings/access-control/modules', ['modules' => $enabledModuleIds])
            ->assertRedirect('/settings/access-control');

        $this->assertTrue($this->companyModuleEnabled((int) $company->id, 'core'));
        $this->assertFalse($this->companyModuleEnabled((int) $company->id, 'assets'));
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'event' => 'company_modules_updated',
        ]);

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/assets')->assertForbidden();
    }

    public function test_planned_module_cannot_be_enabled(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $company = DB::table('companies')->where('code', 'SAMS')->firstOrFail();
        $accountingModuleId = DB::table('modules')->where('key', 'accounting')->value('id');

        $this->actingAs($admin)
            ->from('/settings/access-control')
            ->put('/settings/access-control/modules', ['modules' => [$accountingModuleId]])
            ->assertRedirect('/settings/access-control')
            ->assertSessionHasErrors('modules');

        $this->assertFalse($this->companyModuleEnabled((int) $company->id, 'accounting'));
    }

    public function test_custom_role_matrix_is_enforced_and_survives_reprovisioning(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $staff = User::query()->where('email', 'staff@sams.local')->firstOrFail();
        $company = DB::table('companies')->where('code', 'SAMS')->firstOrFail();
        $staffRole = DB::table('roles')
            ->where('company_id', $company->id)
            ->where('key', 'staff')
            ->firstOrFail();
        $dashboardPermissionId = DB::table('permissions')->where('key', 'core.dashboard.view')->value('id');

        $this->actingAs($admin)
            ->put("/settings/access-control/roles/{$staffRole->id}/permissions", [
                'permissions' => [$dashboardPermissionId],
            ])
            ->assertRedirect('/settings/access-control');

        $this->assertDatabaseHas('roles', [
            'id' => $staffRole->id,
            'company_id' => $company->id,
            'is_customized' => true,
        ]);
        $this->assertSame(1, DB::table('role_permissions')->where('role_id', $staffRole->id)->count());
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $staffRole->id,
            'permission_id' => $dashboardPermissionId,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'event' => 'role_permissions_updated',
            'auditable_id' => $staffRole->id,
        ]);

        app(AccessControlProvisioner::class)->syncCompany($company);

        $this->assertSame(1, DB::table('role_permissions')->where('role_id', $staffRole->id)->count());
        $this->actingAs($staff)->get('/dashboard')->assertOk();
        $this->actingAs($staff)->get('/purchase-requests')->assertForbidden();
    }

    public function test_role_from_another_company_cannot_be_changed(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $otherCompanyId = $this->createOtherCompany();
        app(AccessControlProvisioner::class)->syncCompany($otherCompanyId);

        $otherStaffRoleId = DB::table('roles')
            ->where('company_id', $otherCompanyId)
            ->where('key', 'staff')
            ->value('id');
        $permissionCount = DB::table('role_permissions')->where('role_id', $otherStaffRoleId)->count();
        $dashboardPermissionId = DB::table('permissions')->where('key', 'core.dashboard.view')->value('id');

        $this->actingAs($admin)
            ->put("/settings/access-control/roles/{$otherStaffRoleId}/permissions", [
                'permissions' => [$dashboardPermissionId],
            ])
            ->assertNotFound();

        $this->assertSame(
            $permissionCount,
            DB::table('role_permissions')->where('role_id', $otherStaffRoleId)->count(),
        );
        $this->assertDatabaseHas('roles', [
            'id' => $otherStaffRoleId,
            'company_id' => $otherCompanyId,
            'is_customized' => false,
        ]);
    }

    private function companyModuleEnabled(int $companyId, string $moduleKey): bool
    {
        return (bool) DB::table('company_modules')
            ->join('modules', 'modules.id', '=', 'company_modules.module_id')
            ->where('company_modules.company_id', $companyId)
            ->where('modules.key', $moduleKey)
            ->value('company_modules.is_enabled');
    }

    private function createOtherCompany(): int
    {
        return DB::table('companies')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'code' => 'OTHER',
            'name' => 'Other Tenant',
            'timezone' => 'Asia/Makassar',
            'currency' => 'IDR',
            'locale' => 'id',
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
            'primary_color' => '#5967D8',
            'sidebar_color' => '#182335',
            'accent_color' => '#2F9D8F',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
