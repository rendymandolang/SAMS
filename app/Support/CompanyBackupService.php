<?php

namespace App\Support;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CompanyBackupService
{
    private const DIRECT_TABLES = [
        'branches', 'departments', 'company_user', 'document_sequences', 'suppliers',
        'item_categories', 'units', 'storage_locations', 'items', 'budgets',
        'purchase_requests', 'purchase_orders', 'goods_receipts', 'stock_movements',
        'stock_opnames', 'approval_flows', 'attachments', 'audit_logs', 'asset_registers',
        'asset_maintenances', 'roles', 'company_modules', 'company_user_roles',
        'transaction_period_locks', 'ai_insight_runs', 'ai_company_settings',
        'ai_interactions', 'supplier_catalogs', 'supplier_comparison_runs',
        'data_connections', 'gl_accounts', 'journal_entries', 'accounting_document_sequences',
        'accounting_subledger_sequences', 'ap_invoices', 'ap_payments', 'company_subscriptions',
        'accounting_customers', 'ar_invoices', 'ar_receipts',
        'accounting_bank_accounts', 'bank_statement_imports', 'bank_reconciliations',
        'accounting_settings', 'accounting_tax_codes', 'accounting_posting_rules',
        'accounting_credit_notes', 'fiscal_year_closes',
        'accounting_recurring_templates', 'accounting_recurring_runs',
        'accounting_exchange_rates', 'accounting_fx_revaluations',
        'hr_positions', 'hr_employees', 'hr_leave_types', 'hr_leave_requests', 'hr_employee_documents',
        'company_storage_profiles',
    ];

    public function __construct(private readonly CompanyStorageManager $storageManager) {}

    public function create(int $companyId, int $userId): int
    {
        $snapshot = $this->snapshot($companyId);
        $plain = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $encrypted = $this->encrypter()->encryptString($plain);
        $size = strlen($encrypted);
        $disk = $this->storageManager->writableDisk($companyId);
        $publicId = (string) Str::uuid();
        $path = $this->storageManager->path($companyId, 'backups/'.$publicId.'.ssbackup');
        $reserved = false;
        $stored = false;

        try {
            $this->storageManager->reserveCapacity($companyId, $size);
            $reserved = true;
            Storage::disk($disk)->put($path, $encrypted);
            $stored = true;

            $backupId = DB::table('company_backups')->insertGetId([
                'company_id' => $companyId,
                'public_id' => $publicId,
                'status' => 'ready',
                'disk' => $disk,
                'path' => $path,
                'format' => 'supersoft-json-v1',
                'encryption' => strtolower((string) config('app.cipher', 'AES-256-CBC')),
                'size_bytes' => $size,
                'checksum_sha256' => hash('sha256', $plain),
                'table_count' => count($snapshot['tables']),
                'row_count' => collect($snapshot['tables'])->sum(fn (array $rows): int => count($rows)),
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            if ($stored) {
                try {
                    Storage::disk($disk)->delete($path);
                } catch (Throwable) {
                    // Preserve the original failure while quota accounting is corrected below.
                }
            }
            if ($reserved) {
                $this->storageManager->releaseCapacity($companyId, $size);
            }

            throw $exception;
        }

        $this->verify($companyId, $backupId);

        return $backupId;
    }

    public function verify(int $companyId, int $backupId): object
    {
        $backup = DB::table('company_backups')
            ->where('company_id', $companyId)
            ->where('id', $backupId)
            ->firstOrFail();
        $disk = $this->storageManager->mountStoredDisk($companyId, $backup->disk);

        try {
            $encrypted = Storage::disk($disk)->get($backup->path);
            $plain = $this->encrypter()->decryptString($encrypted);
            if (! hash_equals((string) $backup->checksum_sha256, hash('sha256', $plain))) {
                throw new RuntimeException('Checksum backup tidak sesuai.');
            }

            $payload = json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
            if (($payload['format'] ?? null) !== 'supersoft-json-v1'
                || (int) ($payload['company']['id'] ?? 0) !== $companyId
                || ! is_array($payload['tables'] ?? null)) {
                throw new RuntimeException('Struktur backup tidak valid untuk perusahaan ini.');
            }

            DB::table('company_backups')->where('id', $backup->id)->update([
                'status' => 'verified',
                'verified_at' => now(),
                'verification_status' => 'passed',
                'verification_message' => 'Dekripsi, checksum, identitas perusahaan, dan struktur data berhasil diverifikasi.',
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            DB::table('company_backups')->where('id', $backup->id)->update([
                'status' => 'verification_failed',
                'verified_at' => now(),
                'verification_status' => 'failed',
                'verification_message' => 'Backup tidak dapat diverifikasi. Periksa file, encryption key, dan storage.',
                'updated_at' => now(),
            ]);

            throw $exception;
        }

        return DB::table('company_backups')->where('id', $backup->id)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function snapshot(int $companyId): array
    {
        $company = DB::table('companies')->where('id', $companyId)->firstOrFail();
        $tables = [];

        foreach (self::DIRECT_TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id')) {
                $tables[$table] = DB::table($table)->where('company_id', $companyId)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
            }
        }

        $tables['companies'] = [(array) $company];
        $userIds = collect($tables['company_user'] ?? [])->pluck('user_id');
        $tables['users'] = $userIds->isEmpty() ? [] : DB::table('users')->whereIn('id', $userIds)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();

        $this->addChildren($tables, 'item_unit_conversions', 'item_id', 'items');
        $this->addChildren($tables, 'budget_lines', 'budget_id', 'budgets');
        $this->addChildren($tables, 'purchase_request_items', 'purchase_request_id', 'purchase_requests');
        $this->addChildren($tables, 'purchase_order_items', 'purchase_order_id', 'purchase_orders');
        $this->addChildren($tables, 'goods_receipt_items', 'goods_receipt_id', 'goods_receipts');
        $this->addChildren($tables, 'stock_opname_items', 'stock_opname_id', 'stock_opnames');
        $this->addChildren($tables, 'approval_flow_steps', 'approval_flow_id', 'approval_flows');
        $this->addChildren($tables, 'approval_requests', 'approval_flow_id', 'approval_flows');
        $this->addChildren($tables, 'approval_actions', 'approval_request_id', 'approval_requests');
        $this->addChildren($tables, 'role_permissions', 'role_id', 'roles');
        $this->addChildren($tables, 'supplier_catalog_items', 'supplier_catalog_id', 'supplier_catalogs');
        $this->addChildren($tables, 'journal_entry_lines', 'journal_entry_id', 'journal_entries');
        $this->addChildren($tables, 'ap_invoice_lines', 'ap_invoice_id', 'ap_invoices');
        $this->addChildren($tables, 'ap_payment_allocations', 'ap_payment_id', 'ap_payments');
        $this->addChildren($tables, 'ar_invoice_lines', 'ar_invoice_id', 'ar_invoices');
        $this->addChildren($tables, 'ar_receipt_allocations', 'ar_receipt_id', 'ar_receipts');
        $this->addChildren($tables, 'bank_statement_lines', 'bank_statement_import_id', 'bank_statement_imports');
        $this->addChildren($tables, 'hr_leave_balances', 'employee_id', 'hr_employees');
        $this->addChildren($tables, 'accounting_recurring_template_lines', 'template_id', 'accounting_recurring_templates');

        ksort($tables);

        return [
            'format' => 'supersoft-json-v1',
            'created_at' => now()->toIso8601String(),
            'company' => ['id' => (int) $company->id, 'public_id' => $company->public_id, 'code' => $company->code],
            'tables' => $tables,
        ];
    }

    /** @param array<string, array<int, array<string, mixed>>> $tables */
    private function addChildren(array &$tables, string $table, string $foreignKey, string $parentTable): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $ids = collect($tables[$parentTable] ?? [])->pluck('id');
        $tables[$table] = $ids->isEmpty()
            ? []
            : DB::table($table)->whereIn($foreignKey, $ids)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
    }

    private function encrypter(): Encrypter
    {
        $configuredKey = (string) (config('supersoft.backup_encryption_key') ?: config('app.key'));
        $key = str_starts_with($configuredKey, 'base64:')
            ? base64_decode(substr($configuredKey, 7), true)
            : $configuredKey;

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('Backup encryption key belum dikonfigurasi.');
        }

        return new Encrypter($key, (string) config('app.cipher', 'AES-256-CBC'));
    }
}
