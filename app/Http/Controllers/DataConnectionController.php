<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use App\Support\CompanyContext;
use App\Support\LiveBankRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DataConnectionController extends Controller
{
    public function index(CompanyContext $context): View
    {
        $companyId = $context->id();
        $this->ensureDefaults($companyId);

        return view('data-connections.index', [
            'company' => $context->current(),
            'connections' => DB::table('data_connections')->where('company_id', $companyId)->orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function test(int $connection, CompanyContext $context, LiveBankRateService $bankRateService): RedirectResponse
    {
        $record = DB::table('data_connections')->where('company_id', $context->id())->where('id', $connection)->firstOrFail();
        $startedAt = hrtime(true);

        if ($record->provider_key === 'api_co_id_bank_rate') {
            $result = $bankRateService->probe();
            $success = $result['available'];
            $message = $success ? 'Koneksi berhasil · '.$result['bank_code'].' '.$result['rate_type'].' · '.count($result['rates']).' mata uang' : $result['message'];
        } else {
            $success = false;
            $message = 'Kredensial sandbox/partner belum dikonfigurasi.';
        }

        $elapsedMs = max(1, (int) round((hrtime(true) - $startedAt) / 1_000_000));
        DB::table('data_connections')->where('id', $record->id)->update([
            'status' => $success ? 'connected' : 'needs_configuration',
            'is_active' => $success ? true : $record->is_active,
            'last_tested_at' => now(),
            'last_success_at' => $success ? now() : $record->last_success_at,
            'last_response_ms' => $elapsedMs,
            'last_message' => $message,
            'updated_at' => now(),
        ]);
        AuditLogger::log('data_connection_tested', 'data_connection', (int) $record->id, null, ['provider' => $record->provider_key, 'success' => $success, 'response_ms' => $elapsedMs], $context->id());

        return back()->with($success ? 'status' : 'connection_warning', $message);
    }

    public function toggle(int $connection, CompanyContext $context): RedirectResponse
    {
        $record = DB::table('data_connections')->where('company_id', $context->id())->where('id', $connection)->firstOrFail();
        $active = ! (bool) $record->is_active;

        if ($active && $record->status !== 'connected') {
            return back()->with('connection_warning', 'Uji koneksi hingga berhasil sebelum mengaktifkan provider.');
        }

        DB::table('data_connections')->where('id', $record->id)->update(['is_active' => $active, 'updated_at' => now()]);
        AuditLogger::log('data_connection_toggled', 'data_connection', (int) $record->id, ['is_active' => (bool) $record->is_active], ['is_active' => $active], $context->id());

        return back()->with('status', $active ? 'Koneksi diaktifkan.' : 'Koneksi dinonaktifkan.');
    }

    private function ensureDefaults(int $companyId): void
    {
        $defaults = [
            ['provider_key' => 'api_co_id_bank_rate', 'name' => 'API.co.id · Kurs Bank', 'category' => 'Market Data', 'sync_interval_minutes' => 30, 'settings' => ['bank_code' => config('services.api_co_id.bank_code', 'bri')]],
            ['provider_key' => 'bri_valas_official', 'name' => 'BRIAPI · Valas Resmi', 'category' => 'Banking', 'sync_interval_minutes' => 30, 'settings' => ['environment' => 'sandbox']],
            ['provider_key' => 'supplier_catalog_feed', 'name' => 'Supplier Catalog Feed', 'category' => 'Supplier', 'sync_interval_minutes' => 1440, 'settings' => ['formats' => ['CSV', 'XLSX', 'PDF']]],
            ['provider_key' => 'google_sheets_supplier', 'name' => 'Google Sheets · Supplier', 'category' => 'Supplier', 'sync_interval_minutes' => 360, 'settings' => ['mode' => 'planned']],
        ];

        foreach ($defaults as $default) {
            DB::table('data_connections')->insertOrIgnore([
                'company_id' => $companyId,
                'provider_key' => $default['provider_key'],
                'name' => $default['name'],
                'category' => $default['category'],
                'status' => $default['provider_key'] === 'supplier_catalog_feed' ? 'available' : 'not_tested',
                'is_active' => $default['provider_key'] === 'supplier_catalog_feed',
                'credential_source' => $default['provider_key'] === 'supplier_catalog_feed' ? 'internal' : 'environment',
                'sync_interval_minutes' => $default['sync_interval_minutes'],
                'settings' => json_encode($default['settings']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
