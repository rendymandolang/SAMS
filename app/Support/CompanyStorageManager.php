<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
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

        if ($profile->mode === 'local' && $profile->provider === 'local' && $profile->status === 'active') {
            return 'local';
        }

        if ($profile->mode === 'byoc' && $profile->provider === 's3_compatible' && $profile->status === 'active') {
            return $this->mountS3Disk($profile);
        }

        throw new RuntimeException('Storage perusahaan belum siap menerima dokumen. Aktifkan atau uji koneksi penyimpanan terlebih dahulu.');
    }

    public function mountStoredDisk(int $companyId, string $disk): string
    {
        if ($disk === $this->cloudDiskName($companyId)) {
            return $this->mountS3Disk($this->profile($companyId));
        }

        return $disk;
    }

    public function testConnection(int $companyId): string
    {
        $profile = $this->profile($companyId);

        if ($profile->mode === 'local' && $profile->provider === 'local') {
            $probe = $this->path($companyId, '.supersoft-healthcheck/'.Str::uuid().'.txt');
            $disk = Storage::disk('local');
            $disk->put($probe, 'SuperSoft storage health check');
            $exists = $disk->exists($probe);
            $disk->delete($probe);

            if (! $exists) {
                throw new RuntimeException('Local storage verification failed.');
            }

            return 'Local private storage siap digunakan.';
        }

        if ($profile->mode === 'byoc' && $profile->provider === 's3_compatible') {
            $diskName = $this->mountS3Disk($profile);
            $probe = $this->path($companyId, '.supersoft-healthcheck/'.Str::uuid().'.txt');
            $disk = Storage::disk($diskName);
            $disk->put($probe, 'SuperSoft storage health check');
            $exists = $disk->exists($probe);
            $disk->delete($probe);

            if (! $exists) {
                throw new RuntimeException('Cloud storage verification failed.');
            }

            return 'Koneksi S3-compatible berhasil diverifikasi untuk upload, read, dan delete.';
        }

        throw new RuntimeException('Connector untuk mode penyimpanan ini belum tersedia.');
    }

    public function reserveCapacity(int $companyId, int $bytes): void
    {
        if ($bytes < 0) {
            throw new RuntimeException('Ukuran file tidak valid.');
        }

        DB::transaction(function () use ($companyId, $bytes): void {
            $profile = DB::table('company_storage_profiles')
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->firstOrFail();
            $nextUsage = (int) $profile->used_bytes + $bytes;

            if ($profile->quota_bytes !== null && $nextUsage > (int) $profile->quota_bytes) {
                throw new RuntimeException('Kapasitas penyimpanan perusahaan tidak mencukupi untuk file ini.');
            }

            DB::table('company_storage_profiles')->where('id', $profile->id)->update([
                'used_bytes' => $nextUsage,
                'updated_at' => now(),
            ]);
        });
    }

    public function releaseCapacity(int $companyId, int $bytes): void
    {
        DB::transaction(function () use ($companyId, $bytes): void {
            $profile = DB::table('company_storage_profiles')
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();

            if ($profile) {
                DB::table('company_storage_profiles')->where('id', $profile->id)->update([
                    'used_bytes' => max(0, (int) $profile->used_bytes - max(0, $bytes)),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function path(int $companyId, string $relativePath): string
    {
        $prefix = trim((string) $this->profile($companyId)->root_prefix, '/');

        return $prefix.'/'.ltrim($relativePath, '/');
    }

    private function mountS3Disk(object $profile): string
    {
        $this->assertSafeEndpoint((string) $profile->endpoint);
        $credentials = $this->decryptCredentials((string) $profile->credentials_encrypted);
        $diskName = $this->cloudDiskName((int) $profile->company_id);

        config(["filesystems.disks.{$diskName}" => [
            'driver' => 's3',
            'key' => $credentials['access_key'],
            'secret' => $credentials['secret_key'],
            'region' => $profile->region ?: 'us-east-1',
            'bucket' => $profile->bucket,
            'endpoint' => $profile->endpoint,
            'use_path_style_endpoint' => (bool) $profile->use_path_style_endpoint,
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ]]);
        Storage::forgetDisk($diskName);

        return $diskName;
    }

    /** @return array{access_key:string, secret_key:string} */
    private function decryptCredentials(string $encrypted): array
    {
        try {
            $credentials = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException $exception) {
            throw new RuntimeException('Credential cloud tidak dapat dibaca. Simpan ulang credential perusahaan.', previous: $exception);
        }

        if (blank($credentials['access_key'] ?? null) || blank($credentials['secret_key'] ?? null)) {
            throw new RuntimeException('Credential cloud belum lengkap.');
        }

        return [
            'access_key' => (string) $credentials['access_key'],
            'secret_key' => (string) $credentials['secret_key'],
        ];
    }

    private function assertSafeEndpoint(string $endpoint): void
    {
        $parts = parse_url($endpoint);
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
        $hasDisallowedParts = is_array($parts)
            && (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']));

        if (! in_array($scheme, ['http', 'https'], true)
            || ! is_string($host)
            || $host === ''
            || strtolower($host) === 'localhost'
            || $hasDisallowedParts) {
            throw new RuntimeException('Endpoint cloud tidak valid.');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : gethostbynamel($host);
        if (! $addresses) {
            throw new RuntimeException('Hostname endpoint cloud tidak dapat di-resolve.');
        }

        foreach ($addresses as $address) {
            $isPublic = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if (! $isPublic) {
                throw new RuntimeException('Endpoint cloud tidak boleh mengarah ke jaringan private atau reserved.');
            }
        }
    }

    private function cloudDiskName(int $companyId): string
    {
        return 'company-cloud-'.$companyId;
    }
}
