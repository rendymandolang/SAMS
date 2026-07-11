<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_only_the_authenticated_users_company(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $currentCompany = DB::table('companies')->where('code', 'SAMS')->firstOrFail();
        $currentItemCount = DB::table('items')->where('company_id', $currentCompany->id)->count();

        $otherCompanyId = $this->createOtherCompany();
        $unitId = DB::table('units')->insertGetId([
            'company_id' => $otherCompanyId,
            'code' => 'PCS',
            'name' => 'Pieces',
            'decimal_places' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('items')->insert([
            'company_id' => $otherCompanyId,
            'base_unit_id' => $unitId,
            'public_id' => (string) Str::uuid(),
            'sku' => 'OTHER-ITEM',
            'name' => 'Other Tenant Item',
            'item_type' => 'inventory',
            'minimum_stock' => 0,
            'standard_cost' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('stats', fn (array $stats): bool => $stats['items'] === $currentItemCount);
        $response->assertDontSee('Other Tenant Item');
    }

    public function test_user_cannot_switch_to_a_company_without_membership(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $otherCompanyId = $this->createOtherCompany();

        $response = $this->actingAs($admin)->post('/context/company', [
            'company_id' => $otherCompanyId,
        ]);

        $response->assertForbidden();
    }

    public function test_created_user_is_attached_to_the_active_company(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $company = DB::table('companies')->where('code', 'SAMS')->firstOrFail();

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'Company Scoped User',
            'email' => 'scoped-user@sams.local',
            'role' => 'staff',
            'password' => 'password',
            'password_confirmation' => 'password',
            'is_active' => 1,
        ]);

        $response->assertRedirect('/users');
        $userId = User::query()->where('email', 'scoped-user@sams.local')->value('id');
        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $userId,
            'is_active' => true,
        ]);
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
