<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class CompanyStorageManager
{
    public function profile(int $companyId): object
    {
        return DB::table('company_storage_profiles')
            ->where('company_id', $companyId)
            ->firstOrFail();
    }

    public function writableDisk(int $companyId): string
    {
        $profile = $this->profile($companyId);

        if ($profile->mode !== 'local' || $profile->provider !== 'local' || $profile->status !== 'active') {
            throw new RuntimeException('Storage perusahaan belum siap menerima dokumen. Aktifkan atau uji koneksi penyimpanan terlebih dahulu.');
        }

        return 'local';
    }

    public function path(int $companyId, string $relativePath): string
    {
        $prefix = trim((string) $this->profile($companyId)->root_prefix, '/');

        return $prefix.'/'.ltrim($relativePath, '/');
    }
}
