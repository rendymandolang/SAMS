<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AssetRegisterController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->company();

        $filters = [
            'status' => $request->input('status'),
            'condition' => $request->input('condition'),
            'department_id' => $request->integer('department_id') ?: null,
        ];

        $assets = DB::table('asset_registers')
            ->join('items', 'items.id', '=', 'asset_registers.item_id')
            ->leftJoin('departments', 'departments.id', '=', 'asset_registers.department_id')
            ->leftJoin('storage_locations', 'storage_locations.id', '=', 'asset_registers.storage_location_id')
            ->where('asset_registers.company_id', $company->id)
            ->whereNull('asset_registers.deleted_at')
            ->when($filters['status'], fn ($query, string $status) => $query->where('asset_registers.status', $status))
            ->when($filters['condition'], fn ($query, string $condition) => $query->where('asset_registers.condition', $condition))
            ->when($filters['department_id'], fn ($query, int $departmentId) => $query->where('asset_registers.department_id', $departmentId))
            ->select(
                'asset_registers.*',
                'items.sku',
                'items.name as item_name',
                'departments.code as department_code',
                'departments.name as department_name',
                'storage_locations.code as location_code',
                'storage_locations.name as location_name',
            )
            ->orderByDesc('asset_registers.acquisition_date')
            ->orderByDesc('asset_registers.id')
            ->paginate(10)
            ->withQueryString();

        $summary = [
            'asset_count' => DB::table('asset_registers')->where('company_id', $company->id)->whereNull('deleted_at')->count(),
            'active_count' => DB::table('asset_registers')->where('company_id', $company->id)->where('status', 'active')->whereNull('deleted_at')->count(),
            'watch_count' => DB::table('asset_registers')->where('company_id', $company->id)->whereIn('condition', ['fair', 'poor'])->whereNull('deleted_at')->count(),
            'total_cost' => DB::table('asset_registers')->where('company_id', $company->id)->whereNull('deleted_at')->sum('acquisition_cost'),
        ];

        return view('assets.index', [
            'assets' => $assets,
            'summary' => $summary,
            'filters' => $filters,
            'departments' => $this->departments((int) $company->id),
            'conditions' => $this->conditions(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(): View
    {
        $company = $this->company();

        return view('assets.create', [
            'items' => $this->assetItems((int) $company->id),
            'departments' => $this->departments((int) $company->id),
            'locations' => $this->locations((int) $company->id),
            'conditions' => $this->conditions(),
            'statuses' => $this->statuses(),
            'nextAssetNumber' => $this->previewNextAssetNumber((int) $company->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $branch = $this->branch();

        $validated = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'storage_location_id' => ['nullable', 'integer', 'exists:storage_locations,id'],
            'asset_name' => ['required', 'string', 'max:255'],
            'asset_number' => ['nullable', 'string', 'max:80'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'acquisition_date' => ['required', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'min:0'],
            'condition' => ['required', 'string', 'in:good,fair,poor,repair'],
            'status' => ['required', 'string', 'in:active,maintenance,retired,lost'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $item = DB::table('items')
            ->where('company_id', $company->id)
            ->where('id', $validated['item_id'])
            ->where('item_type', 'asset')
            ->whereNull('deleted_at')
            ->firstOrFail();

        $assetId = DB::transaction(function () use ($company, $branch, $validated, $item) {
            $assetNumber = filled($validated['asset_number'] ?? null)
                ? $validated['asset_number']
                : $this->nextAssetNumber((int) $company->id, (int) $branch->id);

            $assetId = DB::table('asset_registers')->insertGetId([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'department_id' => $validated['department_id'] ?? null,
                'storage_location_id' => $validated['storage_location_id'] ?? null,
                'item_id' => $item->id,
                'goods_receipt_item_id' => null,
                'asset_number' => $assetNumber,
                'asset_name' => $validated['asset_name'],
                'serial_number' => $validated['serial_number'] ?? null,
                'acquisition_date' => $validated['acquisition_date'],
                'acquisition_cost' => $validated['acquisition_cost'],
                'condition' => $validated['condition'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            AuditLogger::log('asset_created', 'asset_register', $assetId, null, ['asset_number' => $assetNumber], (int) $company->id);

            return $assetId;
        });

        return redirect()
            ->route('assets.show', $assetId)
            ->with('status', 'Asset berhasil didaftarkan.');
    }

    public function show(int $asset): View
    {
        $assetRow = $this->findAsset($asset);

        return view('assets.show', ['asset' => $assetRow]);
    }

    public function print(int $asset): View
    {
        $assetRow = $this->findAsset($asset);
        $company = $this->company();
        $branch = DB::table('branches')->where('id', $assetRow->branch_id)->first();

        return view('assets.print', ['asset' => $assetRow, 'company' => $company, 'branch' => $branch]);
    }

    private function findAsset(int $asset): object
    {
        $company = $this->company();

        $assetRow = DB::table('asset_registers')
            ->join('items', 'items.id', '=', 'asset_registers.item_id')
            ->join('users', 'users.id', '=', 'asset_registers.created_by')
            ->leftJoin('departments', 'departments.id', '=', 'asset_registers.department_id')
            ->leftJoin('storage_locations', 'storage_locations.id', '=', 'asset_registers.storage_location_id')
            ->where('asset_registers.company_id', $company->id)
            ->where('asset_registers.id', $asset)
            ->whereNull('asset_registers.deleted_at')
            ->select(
                'asset_registers.*',
                'items.sku',
                'items.name as item_name',
                'items.description as item_description',
                'users.name as creator_name',
                'departments.code as department_code',
                'departments.name as department_name',
                'storage_locations.code as location_code',
                'storage_locations.name as location_name',
            )
            ->first();

        abort_unless($assetRow, 404);

        return $assetRow;
    }

    private function nextAssetNumber(int $companyId, int $branchId): string
    {
        $period = now()->format('Ym');
        $sequence = DB::table('document_sequences')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('document_type', 'asset_register')
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            DB::table('document_sequences')->insert([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'document_type' => 'asset_register',
                'prefix' => 'AST',
                'next_number' => 1,
                'padding' => 5,
                'period_format' => 'Ym',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('document_sequences')
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('document_type', 'asset_register')
                ->lockForUpdate()
                ->first();
        }

        $number = str_pad((string) $sequence->next_number, (int) $sequence->padding, '0', STR_PAD_LEFT);

        DB::table('document_sequences')->where('id', $sequence->id)->update([
            'next_number' => $sequence->next_number + 1,
            'updated_at' => now(),
        ]);

        return "{$sequence->prefix}-{$period}-{$number}";
    }

    private function previewNextAssetNumber(int $companyId): string
    {
        $sequence = DB::table('document_sequences')
            ->where('company_id', $companyId)
            ->where('document_type', 'asset_register')
            ->first();

        $number = str_pad((string) ($sequence?->next_number ?? 1), (int) ($sequence?->padding ?? 5), '0', STR_PAD_LEFT);

        return 'AST-'.now()->format('Ym').'-'.$number;
    }

    private function assetItems(int $companyId)
    {
        return DB::table('items')
            ->where('company_id', $companyId)
            ->where('item_type', 'asset')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    private function departments(int $companyId)
    {
        return DB::table('departments')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    private function locations(int $companyId)
    {
        return DB::table('storage_locations')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    private function conditions(): array
    {
        return ['good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor', 'repair' => 'Repair'];
    }

    private function statuses(): array
    {
        return ['active' => 'Active', 'maintenance' => 'Maintenance', 'retired' => 'Retired', 'lost' => 'Lost'];
    }

    private function company(): object
    {
        return DB::table('companies')->where('is_active', true)->orderBy('id')->firstOrFail();
    }

    private function branch(): object
    {
        return DB::table('branches')->where('is_active', true)->orderBy('id')->firstOrFail();
    }
}
