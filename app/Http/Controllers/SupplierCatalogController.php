<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use App\Support\CompanyContext;
use App\Support\CompanyStorageManager;
use App\Support\SupplierCatalogScanner;
use App\Support\SupplierComparisonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class SupplierCatalogController extends Controller
{
    public function index(Request $request, CompanyContext $context): View
    {
        $company = $context->current();
        $catalogs = DB::table('supplier_catalogs')->join('suppliers', 'suppliers.id', '=', 'supplier_catalogs.supplier_id')->where('supplier_catalogs.company_id', $company->id)->select('supplier_catalogs.*', 'suppliers.name as supplier_name')->orderByDesc('supplier_catalogs.id')->get();
        $suppliers = DB::table('suppliers')->where('company_id', $company->id)->where('is_active', true)->whereNull('deleted_at')->orderBy('name')->get();
        $comparison = null;
        if ($request->integer('comparison')) {
            $comparison = DB::table('supplier_comparison_runs')->where('company_id', $company->id)->where('id', $request->integer('comparison'))->first();
            if ($comparison) {
                $comparison->results = json_decode($comparison->results, true) ?: [];
                $comparison->summary = json_decode($comparison->summary ?? '{}', true) ?: [];
            }
        }
        $comparisonHistory = DB::table('supplier_comparison_runs')->where('company_id', $company->id)->orderByDesc('id')->limit(10)->get();

        return view('supplier-catalogs.index', compact('company', 'catalogs', 'suppliers', 'comparison', 'comparisonHistory'));
    }

    public function store(Request $request, CompanyContext $context, SupplierCatalogScanner $scanner): RedirectResponse
    {
        $company = $context->current();
        $v = $request->validate(['supplier_id' => ['required', 'integer'], 'name' => ['required', 'string', 'max:255'], 'currency' => ['required', 'string', 'size:3'], 'valid_from' => ['nullable', 'date'], 'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'], 'catalog_file' => ['required', 'file', 'max:20480', 'mimes:csv,txt,xlsx,xls,pdf']]);
        abort_unless(DB::table('suppliers')->where('company_id', $company->id)->where('id', $v['supplier_id'])->exists(), 404);
        $file = $request->file('catalog_file');
        $storageManager = app(CompanyStorageManager::class);
        $disk = null;
        $path = null;
        $reserved = false;
        $size = (int) $file->getSize();

        try {
            $disk = $storageManager->writableDisk((int) $company->id);
            $storageManager->reserveCapacity((int) $company->id, $size);
            $reserved = true;
            $path = $file->store($storageManager->path((int) $company->id, 'supplier-catalogs'), $disk);

            if (! is_string($path) || $path === '') {
                throw new RuntimeException('File katalog gagal disimpan.');
            }

            $catalogId = DB::table('supplier_catalogs')->insertGetId([
                'company_id' => $company->id,
                'supplier_id' => $v['supplier_id'],
                'uploaded_by' => auth()->id(),
                'name' => $v['name'],
                'currency' => strtoupper($v['currency']),
                'valid_from' => $v['valid_from'] ?? null,
                'valid_until' => $v['valid_until'] ?? null,
                'original_filename' => $file->getClientOriginalName(),
                'disk' => $disk,
                'stored_path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'file_size' => $size,
                'status' => 'uploaded',
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
                $storageManager->releaseCapacity((int) $company->id, $size);
            }

            throw ValidationException::withMessages(['catalog_file' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            if ($path && $disk) {
                try {
                    Storage::disk($disk)->delete($path);
                } catch (Throwable) {
                    // Preserve the original exception and release the reserved quota below.
                }
            }
            if ($reserved) {
                $storageManager->releaseCapacity((int) $company->id, $size);
            }

            throw $exception;
        }

        $temporaryPath = null;
        try {
            [$scanPath, $temporaryPath] = $this->scanPath($disk, $path);
            $scan = $scanner->scan($scanPath, strtolower($file->getClientOriginalExtension()));
            DB::transaction(function () use ($scan, $catalogId) {
                foreach ($scan['items'] as $item) {
                    DB::table('supplier_catalog_items')->insert(['supplier_catalog_id' => $catalogId, ...collect($item)->except('raw_data')->all(), 'raw_data' => json_encode($item['raw_data'] ?? []), 'status' => 'staged', 'created_at' => now(), 'updated_at' => now()]);
                }DB::table('supplier_catalogs')->where('id', $catalogId)->update(['status' => 'scanned', 'row_count' => count($scan['items']), 'scan_summary' => json_encode($scan['summary']), 'updated_at' => now()]);
            });
            AuditLogger::log('supplier_catalog_scanned', 'supplier_catalog', $catalogId, null, ['rows' => count($scan['items'])], (int) $company->id);
        } catch (Throwable $e) {
            $message = 'Pemindaian katalog gagal. Periksa format file dan konfigurasi penyimpanan.';
            DB::table('supplier_catalogs')->where('id', $catalogId)->update(['status' => 'failed', 'error_message' => $message, 'updated_at' => now()]);
            report($e);

            return redirect()->route('supplier-catalogs.show', $catalogId)->withErrors(['catalog_file' => $message]);
        } finally {
            if ($temporaryPath && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }

        return redirect()->route('supplier-catalogs.show', $catalogId)->with('status', 'Katalog berhasil dipindai. Review baris sebelum publish.');
    }

    public function show(int $catalog, CompanyContext $context): View
    {
        $company = $context->current();
        $catalogRow = DB::table('supplier_catalogs')->join('suppliers', 'suppliers.id', '=', 'supplier_catalogs.supplier_id')->where('supplier_catalogs.company_id', $company->id)->where('supplier_catalogs.id', $catalog)->select('supplier_catalogs.*', 'suppliers.name as supplier_name')->first();
        abort_unless($catalogRow, 404);
        $items = DB::table('supplier_catalog_items')->where('supplier_catalog_id', $catalog)->orderBy('source_row')->paginate(100);

        return view('supplier-catalogs.show', ['catalog' => $catalogRow, 'items' => $items]);
    }

    public function updateItem(Request $request, int $catalog, int $catalogItem, CompanyContext $context): RedirectResponse
    {
        $company = $context->current();
        $row = DB::table('supplier_catalog_items')->join('supplier_catalogs', 'supplier_catalogs.id', '=', 'supplier_catalog_items.supplier_catalog_id')->where('supplier_catalogs.company_id', $company->id)->where('supplier_catalogs.id', $catalog)->where('supplier_catalog_items.id', $catalogItem)->select('supplier_catalog_items.*')->first();
        abort_unless($row, 404);
        $v = $request->validate(['source_name' => ['required', 'string', 'max:255'], 'source_sku' => ['nullable', 'string', 'max:100'], 'price' => ['required', 'numeric', 'min:0.0001'], 'normalized_quantity' => ['required', 'numeric', 'min:0.000001'], 'normalized_unit' => ['required', 'string', 'max:20'], 'minimum_order_quantity' => ['required', 'numeric', 'min:0']]);
        $v['normalized_unit'] = strtoupper($v['normalized_unit']);
        $v['normalized_unit_price'] = $v['price'] / $v['normalized_quantity'];
        DB::table('supplier_catalog_items')->where('id', $row->id)->update([...$v, 'confidence' => 1, 'updated_at' => now()]);

        return back()->with('status', 'Baris katalog diperbarui.');
    }

    public function publish(Request $request, int $catalog, CompanyContext $context): RedirectResponse
    {
        $company = $context->current();
        $catalogRow = DB::table('supplier_catalogs')->where('company_id', $company->id)->where('id', $catalog)->first();
        abort_unless($catalogRow, 404);
        $ids = collect($request->input('item_ids', []))->map(fn ($id) => (int) $id)->filter();
        $query = DB::table('supplier_catalog_items')->where('supplier_catalog_id', $catalog)->where('status', 'staged');
        if ($ids->isNotEmpty()) {
            $query->whereIn('id', $ids);
        }$count = $query->update(['status' => 'published', 'updated_at' => now()]);
        DB::table('supplier_catalogs')->where('id', $catalog)->update(['status' => 'published', 'updated_at' => now()]);
        AuditLogger::log('supplier_catalog_published', 'supplier_catalog', $catalog, null, ['rows' => $count], (int) $company->id);

        return back()->with('status', $count.' baris katalog dipublikasikan.');
    }

    public function compare(Request $request, CompanyContext $context, SupplierComparisonService $service): RedirectResponse
    {
        $v = $request->validate(['query' => ['required', 'string', 'max:255'], 'quantity' => ['required', 'numeric', 'min:0.0001'], 'unit' => ['required', 'in:KG,L,PCS,SET,PACK,BOX,ROLL'], 'budget' => ['nullable', 'numeric', 'min:0']]);
        $result = $service->compare((int) $context->id(), $v['query'], (float) $v['quantity'], $v['unit'], isset($v['budget']) ? (float) $v['budget'] : null);

        return redirect()->route('supplier-catalogs.index', ['comparison' => $result['id']]);
    }

    public function decide(Request $request, int $comparison, CompanyContext $context): RedirectResponse
    {
        $company = $context->current();
        $run = DB::table('supplier_comparison_runs')->where('company_id', $company->id)->where('id', $comparison)->first();
        abort_unless($run, 404);
        $validated = $request->validate(['catalog_item_id' => ['required', 'integer'], 'decision_reason' => ['nullable', 'string', 'max:1000']]);
        $selected = collect(json_decode($run->results, true) ?: [])->firstWhere('catalog_item_id', (int) $validated['catalog_item_id']);
        abort_unless($selected, 422, 'Rekomendasi tidak ditemukan pada analisis ini.');

        DB::table('supplier_comparison_runs')->where('id', $run->id)->update([
            'status' => 'selected',
            'selected_supplier_id' => $selected['supplier_id'],
            'selected_catalog_item_id' => $selected['catalog_item_id'],
            'decision_reason' => $validated['decision_reason'] ?? null,
            'decided_by' => auth()->id(),
            'decided_at' => now(),
            'updated_at' => now(),
        ]);
        AuditLogger::log('supplier_recommendation_selected', 'supplier_comparison_run', (int) $run->id, ['status' => $run->status], ['status' => 'selected', 'supplier_id' => $selected['supplier_id'], 'catalog_item_id' => $selected['catalog_item_id']], (int) $company->id);

        return redirect()->route('supplier-catalogs.index', ['comparison' => $run->id])->with('status', 'Rekomendasi supplier dipilih dan tercatat untuk review procurement.');
    }

    /** @return array{0:string, 1:?string} */
    private function scanPath(string $disk, string $path): array
    {
        if ($disk === 'local') {
            return [Storage::disk($disk)->path($path), null];
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'supersoft-catalog-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Temporary file tidak dapat dibuat untuk pemindaian katalog.');
        }

        try {
            $source = Storage::disk($disk)->readStream($path);
            $target = fopen($temporaryPath, 'wb');
            if (! is_resource($source) || ! is_resource($target)) {
                if (is_resource($source)) {
                    fclose($source);
                }
                if (is_resource($target)) {
                    fclose($target);
                }

                throw new RuntimeException('File cloud tidak dapat disiapkan untuk pemindaian.');
            }

            try {
                if (stream_copy_to_stream($source, $target) === false) {
                    throw new RuntimeException('File cloud gagal disalin untuk pemindaian.');
                }
            } finally {
                fclose($source);
                fclose($target);
            }
        } catch (Throwable $exception) {
            @unlink($temporaryPath);

            throw $exception;
        }

        return [$temporaryPath, $temporaryPath];
    }
}
