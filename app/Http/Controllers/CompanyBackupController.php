<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use App\Support\CompanyBackupService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Throwable;

class CompanyBackupController extends Controller
{
    public function store(CompanyContext $companyContext, CompanyBackupService $backupService): RedirectResponse
    {
        $companyId = $companyContext->id();

        try {
            $backupId = $backupService->create($companyId, (int) auth()->id());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('warning', 'Backup gagal dibuat. Periksa kapasitas, encryption key, dan koneksi storage.');
        }

        AuditLogger::log('company_backup_created', 'company_backup', $backupId, null, [
            'status' => 'verified',
        ], $companyId);

        return back()->with('status', 'Backup terenkripsi berhasil dibuat dan diverifikasi.');
    }

    public function verify(int $backup, CompanyContext $companyContext, CompanyBackupService $backupService): RedirectResponse
    {
        $companyId = $companyContext->id();

        try {
            $row = $backupService->verify($companyId, $backup);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('warning', 'Verifikasi backup gagal. File tidak boleh digunakan untuk restore sebelum masalah diperbaiki.');
        }

        AuditLogger::log('company_backup_verified', 'company_backup', (int) $row->id, null, [
            'verification_status' => $row->verification_status,
        ], $companyId);

        return back()->with('status', 'Integritas backup berhasil diverifikasi kembali.');
    }
}
