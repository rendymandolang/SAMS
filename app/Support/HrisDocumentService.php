<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class HrisDocumentService
{
    public function __construct(private readonly CompanyStorageManager $storageManager) {}

    public function store(int $companyId, int $employeeId, int $userId, string $documentType, UploadedFile $file): int
    {
        abort_unless(DB::table('hr_employees')->where('company_id', $companyId)->where('id', $employeeId)->exists(), 404);
        $raw = file_get_contents($file->getRealPath());
        if (! is_string($raw)) {
            throw new RuntimeException('Dokumen karyawan tidak dapat dibaca.');
        }
        $encrypted = Crypt::encryptString(base64_encode($raw));
        $size = strlen($encrypted);
        $disk = $this->storageManager->writableDisk($companyId);
        $path = $this->storageManager->path($companyId, 'hris/employees/'.$employeeId.'/'.Str::uuid().'.shrisdoc');
        $reserved = false;
        $stored = false;
        try {
            $this->storageManager->reserveCapacity($companyId, $size);
            $reserved = true;
            Storage::disk($disk)->put($path, $encrypted);
            $stored = true;

            return DB::table('hr_employee_documents')->insertGetId([
                'company_id' => $companyId, 'employee_id' => $employeeId, 'document_type' => $documentType,
                'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'disk' => $disk, 'path' => $path,
                'size_bytes' => $size, 'checksum_sha256' => hash('sha256', $raw), 'encryption' => 'laravel-encrypter',
                'uploaded_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            if ($stored) {
                try {
                    Storage::disk($disk)->delete($path);
                } catch (Throwable) {
                    // Preserve the original storage or database exception.
                }
            }
            if ($reserved) {
                $this->storageManager->releaseCapacity($companyId, $size);
            }
            throw $exception;
        }
    }

    /** @return array{document:object,content:string} */
    public function read(int $companyId, int $documentId): array
    {
        $document = DB::table('hr_employee_documents')->where('company_id', $companyId)->where('id', $documentId)->firstOrFail();
        $disk = $this->storageManager->mountStoredDisk($companyId, $document->disk);
        $encrypted = Storage::disk($disk)->get($document->path);
        $content = base64_decode(Crypt::decryptString($encrypted), true);
        if (! is_string($content) || ! hash_equals($document->checksum_sha256, hash('sha256', $content))) {
            throw new RuntimeException('Integritas dokumen karyawan tidak dapat diverifikasi.');
        }

        return compact('document', 'content');
    }
}
