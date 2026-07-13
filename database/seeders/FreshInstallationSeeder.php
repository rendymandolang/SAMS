<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\AccessControlProvisioner;
use App\Support\ModuleCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class FreshInstallationSeeder extends Seeder
{
    public function run(): void
    {
        $adminConfig = config('supersoft.initial_admin');

        if (app()->isProduction() && blank($adminConfig['password'])) {
            throw new RuntimeException('INITIAL_ADMIN_PASSWORD wajib diisi untuk instalasi production.');
        }

        $admin = User::query()->create([
            'name' => $adminConfig['name'],
            'email' => $adminConfig['email'],
            'password' => $adminConfig['password'] ?: Str::password(24),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $now = now();
        $companyId = DB::table('companies')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'code' => 'SSGI',
            'name' => 'PT Supersoft Global Investama',
            'legal_name' => 'PT Supersoft Global Investama',
            'timezone' => 'Asia/Makassar',
            'currency' => 'IDR',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'company_id' => $companyId,
            'public_id' => (string) Str::uuid(),
            'code' => 'HO',
            'name' => 'Head Office',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('company_user')->insert([
            'company_id' => $companyId,
            'user_id' => $admin->id,
            'branch_id' => $branchId,
            'department_id' => null,
            'is_default' => true,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $provisioner = app(AccessControlProvisioner::class);
        $provisioner->syncAllCompanies();

        $activeKeys = collect(ModuleCatalog::modules())
            ->filter(fn (array $module) => $module['status'] === 'active')
            ->keys();

        DB::table('company_modules')
            ->where('company_id', $companyId)
            ->whereIn('module_id', DB::table('modules')->whereIn('key', $activeKeys)->select('id'))
            ->update(['is_enabled' => true, 'updated_at' => now()]);

        $provisioner->syncCompany($companyId);
    }
}
