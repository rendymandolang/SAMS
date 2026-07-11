<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_company_settings(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@sams.local')->firstOrFail();

        $response = $this->actingAs($admin)->get('/settings/company');

        $response->assertOk();
        $response->assertSee('Pengaturan Perusahaan');
        $response->assertSee('Bahasa Indonesia');
        $response->assertSee('English');
        $response->assertSee('Soft Indigo');
    }

    public function test_super_admin_can_update_identity_locale_theme_and_logo(): void
    {
        Storage::fake('public');
        $this->seed();
        $admin = User::query()->where('email', 'admin@sams.local')->firstOrFail();

        $response = $this->actingAs($admin)->put('/settings/company', [
            'name' => 'SuperSoft Indonesia',
            'legal_name' => 'PT SuperSoft Teknologi Indonesia',
            'tax_number' => '99.999.999.9-999.999',
            'address' => 'Manado, Sulawesi Utara',
            'phone' => '+62 811 222 333',
            'email' => 'office@supersoft.test',
            'locale' => 'en',
            'timezone' => 'Asia/Makassar',
            'currency' => 'IDR',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'primary_color' => '#3b74b7',
            'sidebar_color' => '#15283b',
            'accent_color' => '#2fa89a',
            'logo' => UploadedFile::fake()->image('supersoft-logo.png', 320, 320),
        ]);

        $response->assertRedirect('/settings/company');
        $this->assertDatabaseHas('companies', [
            'code' => 'SAMS',
            'name' => 'SuperSoft Indonesia',
            'legal_name' => 'PT SuperSoft Teknologi Indonesia',
            'locale' => 'en',
            'primary_color' => '#3B74B7',
            'sidebar_color' => '#15283B',
            'accent_color' => '#2FA89A',
        ]);

        $company = DB::table('companies')->where('code', 'SAMS')->firstOrFail();
        $this->assertNotNull($company->logo_path);
        Storage::disk('public')->assertExists($company->logo_path);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'event' => 'company_settings_updated',
            'auditable_type' => 'company',
            'auditable_id' => $company->id,
        ]);
    }

    public function test_company_settings_reject_unsupported_language_and_unsafe_colors(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@sams.local')->firstOrFail();

        $response = $this->actingAs($admin)
            ->from('/settings/company')
            ->put('/settings/company', [
                'name' => 'Unsafe Update',
                'locale' => 'fr',
                'timezone' => 'Asia/Makassar',
                'currency' => 'IDR',
                'date_format' => 'd/m/Y',
                'time_format' => 'H:i',
                'primary_color' => 'red; background:url(test)',
                'sidebar_color' => '#171A2F',
                'accent_color' => '#20C997',
            ]);

        $response->assertRedirect('/settings/company');
        $response->assertSessionHasErrors(['locale', 'primary_color']);
        $this->assertDatabaseMissing('companies', ['name' => 'Unsafe Update']);
    }

    public function test_non_super_admin_cannot_open_company_settings(): void
    {
        $this->seed();
        $warehouse = User::query()->where('email', 'warehouse@sams.local')->firstOrFail();

        $response = $this->actingAs($warehouse)->get('/settings/company');

        $response->assertForbidden();
    }
}
