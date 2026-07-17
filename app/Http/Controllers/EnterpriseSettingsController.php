<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use App\Support\CompanyContext;
use App\Support\CompanyStorageManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class EnterpriseSettingsController extends Controller
{
    public function index(CompanyContext $companyContext, CompanyStorageManager $storageManager): View
    {
        $company = $companyContext->current();
        $this->ensureRecords((int) $company->id);

        return view('settings.enterprise', [
            'company' => $company,
            'subscription' => DB::table('company_subscriptions')->where('company_id', $company->id)->first(),
            'storage' => DB::table('company_storage_profiles')->where('company_id', $company->id)->first(),
            'storageUsage' => $storageManager->usage((int) $company->id),
            'backups' => DB::table('company_backups')
                ->leftJoin('users', 'users.id', '=', 'company_backups.created_by')
                ->where('company_backups.company_id', $company->id)
                ->select('company_backups.*', 'users.name as creator_name')
                ->orderByDesc('company_backups.id')
                ->limit(10)
                ->get(),
            'modules' => DB::table('modules')
                ->join('company_modules', 'company_modules.module_id', '=', 'modules.id')
                ->where('company_modules.company_id', $company->id)
                ->select('modules.key', 'modules.name', 'modules.status', 'company_modules.is_licensed', 'company_modules.is_enabled', 'company_modules.licensed_until')
                ->orderBy('modules.sort_order')
                ->get(),
        ]);
    }

    public function updateStorage(Request $request, CompanyContext $companyContext): RedirectResponse
    {
        $companyId = $companyContext->id();
        $this->ensureRecords($companyId);
        $profile = DB::table('company_storage_profiles')->where('company_id', $companyId)->firstOrFail();
        $validated = $request->validate([
            'mode' => ['required', Rule::in(['local', 'byoc', 'managed'])],
            'provider' => ['required', Rule::in(['local', 's3_compatible', 'supersoft_cloud'])],
            'bucket' => ['nullable', 'string', 'max:255', 'regex:/\A[a-zA-Z0-9._-]+\z/'],
            'region' => ['nullable', 'string', 'max:100'],
            'endpoint' => ['nullable', 'url:http,https', 'max:500'],
            'use_path_style_endpoint' => ['nullable', 'boolean'],
            'access_key' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:1000'],
            'storage_quota_gb' => ['nullable', 'integer', 'min:1', 'max:1048576'],
        ]);

        $validProvider = match ($validated['mode']) {
            'local' => $validated['provider'] === 'local',
            'byoc' => $validated['provider'] === 's3_compatible',
            'managed' => $validated['provider'] === 'supersoft_cloud',
        };
        if (! $validProvider) {
            throw ValidationException::withMessages(['provider' => 'Provider tidak sesuai dengan mode penyimpanan yang dipilih.']);
        }

        $hasAccessKey = filled($validated['access_key'] ?? null);
        $hasSecretKey = filled($validated['secret_key'] ?? null);
        if ($hasAccessKey !== $hasSecretKey) {
            throw ValidationException::withMessages(['access_key' => 'Access key dan secret key harus diisi bersamaan.']);
        }

        if ($validated['mode'] === 'byoc') {
            if (blank($validated['bucket'] ?? null) || blank($validated['endpoint'] ?? null)) {
                throw ValidationException::withMessages(['bucket' => 'Bucket dan endpoint wajib diisi untuk BYOC.']);
            }
            if (app()->isProduction() && ! str_starts_with(strtolower($validated['endpoint']), 'https://')) {
                throw ValidationException::withMessages(['endpoint' => 'Endpoint BYOC production wajib menggunakan HTTPS.']);
            }
            if (! $profile->credentials_encrypted && (blank($validated['access_key'] ?? null) || blank($validated['secret_key'] ?? null))) {
                throw ValidationException::withMessages(['access_key' => 'Access key dan secret key wajib diisi untuk koneksi BYOC baru.']);
            }
        }

        $encryptedCredentials = $profile->credentials_encrypted;
        if (filled($validated['access_key'] ?? null) || filled($validated['secret_key'] ?? null)) {
            $encryptedCredentials = Crypt::encryptString(json_encode([
                'access_key' => $validated['access_key'] ?? '',
                'secret_key' => $validated['secret_key'] ?? '',
            ], JSON_THROW_ON_ERROR));
        } elseif ($validated['mode'] !== 'byoc') {
            $encryptedCredentials = null;
        }

        $old = [
            'mode' => $profile->mode,
            'provider' => $profile->provider,
            'bucket' => $profile->bucket,
            'region' => $profile->region,
            'endpoint' => $profile->endpoint,
            'use_path_style_endpoint' => (bool) $profile->use_path_style_endpoint,
            'quota_bytes' => $profile->quota_bytes,
        ];
        $new = [
            'mode' => $validated['mode'],
            'provider' => $validated['provider'],
            'status' => $validated['mode'] === 'local' ? 'active' : 'pending_test',
            'bucket' => $validated['mode'] === 'byoc' ? ($validated['bucket'] ?? null) : null,
            'region' => $validated['mode'] === 'byoc' ? ($validated['region'] ?? null) : null,
            'endpoint' => $validated['mode'] === 'byoc' ? ($validated['endpoint'] ?? null) : null,
            'use_path_style_endpoint' => $request->boolean('use_path_style_endpoint'),
            'root_prefix' => 'companies/'.$companyId,
            'credentials_encrypted' => $encryptedCredentials,
            'quota_bytes' => isset($validated['storage_quota_gb']) ? $validated['storage_quota_gb'] * 1024 * 1024 * 1024 : null,
            'last_tested_at' => null,
            'last_test_message' => null,
            'updated_at' => now(),
        ];

        DB::table('company_storage_profiles')->where('id', $profile->id)->update($new);
        AuditLogger::log('company_storage_updated', 'company_storage_profile', (int) $profile->id, $old, [
            'mode' => $new['mode'],
            'provider' => $new['provider'],
            'bucket' => $new['bucket'],
            'region' => $new['region'],
            'endpoint' => $new['endpoint'],
            'use_path_style_endpoint' => $new['use_path_style_endpoint'],
            'quota_bytes' => $new['quota_bytes'],
            'credentials_changed' => $encryptedCredentials !== $profile->credentials_encrypted,
        ], $companyId);

        return redirect()->route('settings.enterprise')->with('status', 'Konfigurasi penyimpanan berhasil disimpan.');
    }

    public function testStorage(CompanyContext $companyContext, CompanyStorageManager $storageManager): RedirectResponse
    {
        $companyId = $companyContext->id();
        $profile = DB::table('company_storage_profiles')->where('company_id', $companyId)->firstOrFail();

        if ($profile->mode === 'managed') {
            $status = 'pending_connector';
            $message = 'SuperSoft Managed Cloud akan aktif setelah infrastruktur layanan tersedia.';
        } else {
            try {
                $message = $storageManager->testConnection($companyId);
                $status = 'active';
            } catch (Throwable) {
                $status = 'failed';
                $message = 'Connection test gagal. Periksa endpoint, bucket, region, credential, dan akses jaringan.';
            }
        }

        DB::table('company_storage_profiles')->where('id', $profile->id)->update([
            'status' => $status,
            'last_tested_at' => now(),
            'last_test_message' => $message,
            'updated_at' => now(),
        ]);
        AuditLogger::log('company_storage_tested', 'company_storage_profile', (int) $profile->id, null, [
            'provider' => $profile->provider,
            'status' => $status,
        ], $companyId);

        return back()->with($status === 'active' ? 'status' : 'warning', $message);
    }

    private function ensureRecords(int $companyId): void
    {
        DB::table('company_subscriptions')->insertOrIgnore([
            'company_id' => $companyId,
            'plan_code' => 'unassigned',
            'license_model' => 'trial',
            'billing_cycle' => 'none',
            'status' => 'pending',
            'starts_on' => today()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('company_storage_profiles')->insertOrIgnore([
            'company_id' => $companyId,
            'mode' => 'local',
            'provider' => 'local',
            'status' => 'active',
            'root_prefix' => 'companies/'.$companyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
