<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CompanyStorageManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class EnterpriseSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_subscription_entitlements_and_storage(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@sams.local')->firstOrFail();
        $this->actingAs($admin)
            ->get('/settings/enterprise')
            ->assertOk()
            ->assertSee('License & Data Storage', false)
            ->assertSee('Development Suite')
            ->assertSee('Module Entitlement')
            ->assertSee('Local Private Storage');
    }

    public function test_byoc_credentials_are_encrypted_and_excluded_from_audit_values(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@sams.local')->firstOrFail();
        $otherCompanyId = DB::table('companies')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'code' => 'OTHER',
            'name' => 'Other Company',
            'timezone' => 'Asia/Makassar',
            'currency' => 'IDR',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('company_storage_profiles')->insert([
            'company_id' => $otherCompanyId,
            'mode' => 'local',
            'provider' => 'local',
            'status' => 'active',
            'root_prefix' => 'companies/'.$otherCompanyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->put('/settings/enterprise/storage', [
            'mode' => 'byoc',
            'provider' => 's3_compatible',
            'bucket' => 'supersoft-test-company',
            'region' => 'id-bali-1',
            'endpoint' => 'https://storage.example.test',
            'access_key' => 'company-access-key',
            'secret_key' => 'company-secret-key',
            'use_path_style_endpoint' => '1',
            'storage_quota_gb' => 100,
        ])->assertRedirect('/settings/enterprise');

        $profile = DB::table('company_storage_profiles')->firstOrFail();
        $this->assertSame('pending_test', $profile->status);
        $this->assertTrue((bool) $profile->use_path_style_endpoint);
        $this->assertStringNotContainsString('company-access-key', $profile->credentials_encrypted);
        $this->assertStringNotContainsString('company-secret-key', $profile->credentials_encrypted);

        $credentials = json_decode(Crypt::decryptString($profile->credentials_encrypted), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('company-access-key', $credentials['access_key']);
        $this->assertSame('company-secret-key', $credentials['secret_key']);
        $this->assertDatabaseHas('company_storage_profiles', [
            'company_id' => $otherCompanyId,
            'mode' => 'local',
            'provider' => 'local',
            'status' => 'active',
        ]);

        $audit = DB::table('audit_logs')->where('event', 'company_storage_updated')->firstOrFail();
        $this->assertStringNotContainsString('company-access-key', (string) $audit->new_values);
        $this->assertStringNotContainsString('company-secret-key', (string) $audit->new_values);
    }

    public function test_local_storage_connection_can_be_verified(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@sams.local')->firstOrFail();

        $this->actingAs($admin)
            ->post('/settings/enterprise/storage/test')
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('company_storage_profiles', [
            'provider' => 'local',
            'status' => 'active',
            'last_test_message' => 'Local private storage siap digunakan.',
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'company_storage_tested']);
    }

    public function test_storage_capacity_is_reserved_atomically_and_cannot_exceed_quota(): void
    {
        $this->seed();
        $profile = DB::table('company_storage_profiles')->firstOrFail();
        DB::table('company_storage_profiles')->where('id', $profile->id)->update([
            'used_bytes' => 90,
            'quota_bytes' => 100,
        ]);
        $manager = app(CompanyStorageManager::class);

        try {
            $manager->reserveCapacity((int) $profile->company_id, 11);
            $this->fail('Quota overflow should have been rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Kapasitas penyimpanan perusahaan tidak mencukupi untuk file ini.', $exception->getMessage());
        }

        $this->assertSame(90, (int) DB::table('company_storage_profiles')->value('used_bytes'));
        $manager->reserveCapacity((int) $profile->company_id, 10);
        $this->assertSame(100, (int) DB::table('company_storage_profiles')->value('used_bytes'));
        $manager->releaseCapacity((int) $profile->company_id, 10);
        $this->assertSame(90, (int) DB::table('company_storage_profiles')->value('used_bytes'));
    }

    public function test_cloud_connection_rejects_private_network_endpoints_without_exposing_details(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@sams.local')->firstOrFail();
        DB::table('company_storage_profiles')->update([
            'mode' => 'byoc',
            'provider' => 's3_compatible',
            'status' => 'pending_test',
            'bucket' => 'private-test',
            'region' => 'us-east-1',
            'endpoint' => 'http://127.0.0.1:9000',
            'use_path_style_endpoint' => true,
            'credentials_encrypted' => Crypt::encryptString(json_encode([
                'access_key' => 'private-access',
                'secret_key' => 'private-secret',
            ], JSON_THROW_ON_ERROR)),
        ]);

        $this->actingAs($admin)
            ->post('/settings/enterprise/storage/test')
            ->assertRedirect()
            ->assertSessionHas('warning', 'Connection test gagal. Periksa endpoint, bucket, region, credential, dan akses jaringan.');

        $profile = DB::table('company_storage_profiles')->firstOrFail();
        $this->assertSame('failed', $profile->status);
        $this->assertStringNotContainsString('127.0.0.1', (string) $profile->last_test_message);
        $this->assertStringNotContainsString('private-secret', (string) $profile->last_test_message);
    }

    public function test_company_cannot_activate_an_unlicensed_module(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@sams.local')->firstOrFail();
        $accountingId = DB::table('modules')->where('key', 'accounting')->value('id');
        $coreId = DB::table('modules')->where('key', 'core')->value('id');
        DB::table('company_modules')->where('module_id', $accountingId)->update([
            'is_licensed' => false,
            'is_enabled' => false,
        ]);

        $this->actingAs($admin)
            ->from('/settings/access-control')
            ->put('/settings/access-control/modules', ['modules' => [$coreId, $accountingId]])
            ->assertRedirect('/settings/access-control')
            ->assertSessionHasErrors('modules');

        $this->assertDatabaseHas('company_modules', [
            'module_id' => $accountingId,
            'is_licensed' => false,
            'is_enabled' => false,
        ]);
    }

    public function test_expired_subscription_blocks_paid_modules_but_keeps_core_available(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@sams.local')->firstOrFail();
        DB::table('company_subscriptions')->update([
            'status' => 'active',
            'expires_on' => today()->subDay()->toDateString(),
            'grace_ends_on' => today()->subDay()->toDateString(),
        ]);

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/accounting')->assertForbidden();
    }
}
