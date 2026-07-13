<?php

namespace App\Http\Controllers;

use App\Support\AccessManager;
use App\Support\AuditLogger;
use App\Support\CompanyContext;
use App\Support\CompanyStorageManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AttachmentController extends Controller
{
    private const TYPES = [
        'purchase_request' => [
            'table' => 'purchase_requests',
            'route' => 'purchase-requests.show',
            'label' => 'Purchase Request',
            'module' => 'procurement',
            'view_permission' => 'procurement.pr.view',
            'manage_permission' => 'procurement.pr.manage',
        ],
        'purchase_order' => [
            'table' => 'purchase_orders',
            'route' => 'purchase-orders.show',
            'label' => 'Purchase Order',
            'module' => 'procurement',
            'view_permission' => 'procurement.po.view',
            'manage_permission' => 'procurement.po.manage',
        ],
        'goods_receipt' => [
            'table' => 'goods_receipts',
            'route' => 'goods-receipts.show',
            'label' => 'Goods Receipt',
            'module' => 'inventory',
            'view_permission' => 'inventory.gr.view',
            'manage_permission' => 'inventory.gr.manage',
        ],
        'asset_register' => [
            'table' => 'asset_registers',
            'route' => 'assets.show',
            'label' => 'Asset',
            'module' => 'assets',
            'view_permission' => 'assets.register.view',
            'manage_permission' => 'assets.register.manage',
        ],
        'asset_maintenance' => [
            'table' => 'asset_maintenances',
            'route' => 'asset-maintenances.show',
            'label' => 'Asset Maintenance',
            'module' => 'assets',
            'view_permission' => 'assets.maintenance.view',
            'manage_permission' => 'assets.maintenance.manage',
        ],
    ];

    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        $meta = $this->meta($type);
        $this->authorizeType($meta, true);
        $entity = $this->entity($type, $id);

        $validated = $request->validate([
            'attachment' => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt'],
        ]);

        $file = $validated['attachment'];
        $storageManager = app(CompanyStorageManager::class);
        $disk = null;
        $path = null;
        $reserved = false;
        $size = (int) $file->getSize();

        try {
            $disk = $storageManager->writableDisk((int) $entity->company_id);
            $storageManager->reserveCapacity((int) $entity->company_id, $size);
            $reserved = true;
            $directory = $storageManager->path((int) $entity->company_id, "attachments/{$type}/{$id}");
            $path = $file->store($directory, $disk);

            if (! is_string($path) || $path === '') {
                throw new RuntimeException('File gagal disimpan.');
            }

            $attachmentId = DB::table('attachments')->insertGetId([
                'company_id' => $entity->company_id,
                'attachable_type' => $type,
                'attachable_id' => $id,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $size,
                'uploaded_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (RuntimeException $exception) {
            if ($path && $disk) {
                try {
                    Storage::disk($disk)->delete($path);
                } catch (Throwable) {
                    // Quota is still released so the company is not charged for a failed request.
                }
            }
            if ($reserved) {
                $storageManager->releaseCapacity((int) $entity->company_id, $size);
            }

            throw ValidationException::withMessages(['attachment' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            if ($path && $disk) {
                try {
                    Storage::disk($disk)->delete($path);
                } catch (Throwable) {
                    // Preserve the original exception and release the reserved quota below.
                }
            }
            if ($reserved) {
                $storageManager->releaseCapacity((int) $entity->company_id, $size);
            }

            throw $exception;
        }

        AuditLogger::log('attachment_uploaded', $type, $id, null, [
            'attachment_id' => $attachmentId,
            'original_name' => $file->getClientOriginalName(),
        ], (int) $entity->company_id);

        return redirect()
            ->route($meta['route'], $id)
            ->with('status', $meta['label'].' attachment berhasil diupload.');
    }

    public function download(int $attachment): StreamedResponse
    {
        $row = $this->findAttachment($attachment);
        $this->authorizeType($this->meta($row->attachable_type), false);
        $disk = app(CompanyStorageManager::class)->mountStoredDisk((int) $row->company_id, $row->disk);

        abort_unless(Storage::disk($disk)->exists($row->path), 404);

        return Storage::disk($disk)->download($row->path, $row->original_name);
    }

    public function destroy(int $attachment): RedirectResponse
    {
        $row = $this->findAttachment($attachment);
        $meta = $this->meta($row->attachable_type);
        $this->authorizeType($meta, true);
        $storageManager = app(CompanyStorageManager::class);
        $disk = $storageManager->mountStoredDisk((int) $row->company_id, $row->disk);

        Storage::disk($disk)->delete($row->path);

        DB::table('attachments')->where('id', $row->id)->delete();
        $storageManager->releaseCapacity((int) $row->company_id, (int) $row->size);

        AuditLogger::log('attachment_deleted', $row->attachable_type, (int) $row->attachable_id, [
            'attachment_id' => $row->id,
            'original_name' => $row->original_name,
        ], null, (int) $row->company_id);

        return redirect()
            ->route($meta['route'], $row->attachable_id)
            ->with('status', 'Attachment berhasil dihapus.');
    }

    public static function listFor(string $type, int $id)
    {
        return DB::table('attachments')
            ->join('users', 'users.id', '=', 'attachments.uploaded_by')
            ->where('attachments.attachable_type', $type)
            ->where('attachments.attachable_id', $id)
            ->select('attachments.*', 'users.name as uploader_name')
            ->orderByDesc('attachments.created_at')
            ->orderByDesc('attachments.id')
            ->get();
    }

    private function meta(string $type): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type];
    }

    private function entity(string $type, int $id): object
    {
        $meta = $this->meta($type);
        $company = app(CompanyContext::class)->current();

        $entity = DB::table($meta['table'])
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        abort_unless($entity, 404);

        return $entity;
    }

    private function findAttachment(int $attachment): object
    {
        $company = app(CompanyContext::class)->current();

        $row = DB::table('attachments')
            ->where('company_id', $company->id)
            ->where('id', $attachment)
            ->first();

        abort_unless($row, 404);

        return $row;
    }

    private function authorizeType(array $meta, bool $manage): void
    {
        $access = app(AccessManager::class);
        $permission = $manage ? $meta['manage_permission'] : $meta['view_permission'];

        abort_unless($access->moduleEnabled($meta['module']) && $access->allows($permission), 403);
    }
}
