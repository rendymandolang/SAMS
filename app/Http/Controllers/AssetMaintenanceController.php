<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AssetMaintenanceController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->company();
        $filters = [
            'status' => $request->input('status'),
            'priority' => $request->input('priority'),
        ];

        $maintenances = DB::table('asset_maintenances')
            ->join('asset_registers', 'asset_registers.id', '=', 'asset_maintenances.asset_register_id')
            ->join('users', 'users.id', '=', 'asset_maintenances.requested_by')
            ->where('asset_maintenances.company_id', $company->id)
            ->whereNull('asset_maintenances.deleted_at')
            ->when($filters['status'], fn ($query, string $status) => $query->where('asset_maintenances.status', $status))
            ->when($filters['priority'], fn ($query, string $priority) => $query->where('asset_maintenances.priority', $priority))
            ->select(
                'asset_maintenances.*',
                'asset_registers.asset_number',
                'asset_registers.asset_name',
                'users.name as requester_name',
            )
            ->orderByRaw("CASE asset_maintenances.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
            ->orderByDesc('asset_maintenances.request_date')
            ->paginate(10)
            ->withQueryString();

        $summary = [
            'open_count' => DB::table('asset_maintenances')->where('company_id', $company->id)->where('status', 'open')->whereNull('deleted_at')->count(),
            'in_progress_count' => DB::table('asset_maintenances')->where('company_id', $company->id)->where('status', 'in_progress')->whereNull('deleted_at')->count(),
            'completed_count' => DB::table('asset_maintenances')->where('company_id', $company->id)->where('status', 'completed')->whereNull('deleted_at')->count(),
            'actual_cost' => DB::table('asset_maintenances')->where('company_id', $company->id)->whereNull('deleted_at')->sum('actual_cost'),
        ];

        return view('asset_maintenances.index', [
            'maintenances' => $maintenances,
            'summary' => $summary,
            'filters' => $filters,
            'statuses' => $this->statuses(),
            'priorities' => $this->priorities(),
        ]);
    }

    public function create(int $asset): View
    {
        return view('asset_maintenances.create', [
            'asset' => $this->findAsset($asset),
            'types' => $this->types(),
            'priorities' => $this->priorities(),
        ]);
    }

    public function store(Request $request, int $asset): RedirectResponse
    {
        $assetRow = $this->findAsset($asset);
        $validated = $request->validate([
            'maintenance_type' => ['required', 'string', 'in:corrective,preventive,inspection,calibration'],
            'priority' => ['required', 'string', 'in:low,normal,high,urgent'],
            'request_date' => ['required', 'date'],
            'scheduled_date' => ['nullable', 'date', 'after_or_equal:request_date'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'issue_description' => ['required', 'string', 'max:2000'],
        ]);

        $maintenanceId = DB::transaction(function () use ($assetRow, $validated) {
            $now = now();
            $documentNumber = $this->nextDocumentNumber((int) $assetRow->company_id, (int) $assetRow->branch_id);

            $maintenanceId = DB::table('asset_maintenances')->insertGetId([
                'company_id' => $assetRow->company_id,
                'branch_id' => $assetRow->branch_id,
                'asset_register_id' => $assetRow->id,
                'requested_by' => auth()->id(),
                'completed_by' => null,
                'document_number' => $documentNumber,
                'maintenance_type' => $validated['maintenance_type'],
                'priority' => $validated['priority'],
                'status' => 'open',
                'request_date' => $validated['request_date'],
                'scheduled_date' => $validated['scheduled_date'] ?? null,
                'completed_date' => null,
                'vendor_name' => $validated['vendor_name'] ?? null,
                'estimated_cost' => $validated['estimated_cost'] ?? 0,
                'actual_cost' => 0,
                'issue_description' => $validated['issue_description'],
                'resolution_notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('asset_registers')->where('id', $assetRow->id)->update([
                'status' => 'maintenance',
                'updated_at' => $now,
            ]);

            AuditLogger::log('asset_maintenance_created', 'asset_maintenance', $maintenanceId, null, ['document_number' => $documentNumber], (int) $assetRow->company_id);

            return $maintenanceId;
        });

        return redirect()->route('asset-maintenances.show', $maintenanceId)->with('status', 'Maintenance work order berhasil dibuat.');
    }

    public function show(int $maintenance): View
    {
        return view('asset_maintenances.show', ['maintenance' => $this->findMaintenance($maintenance)]);
    }

    public function complete(Request $request, int $maintenance): RedirectResponse
    {
        $maintenanceRow = $this->findMaintenance($maintenance);

        if ($maintenanceRow->status === 'completed') {
            return redirect()->route('asset-maintenances.show', $maintenanceRow->id)->with('status', 'Maintenance sudah completed.');
        }

        $validated = $request->validate([
            'completed_date' => ['required', 'date'],
            'actual_cost' => ['nullable', 'numeric', 'min:0'],
            'resolution_notes' => ['required', 'string', 'max:2000'],
            'asset_condition' => ['required', 'string', 'in:good,fair,poor,repair'],
            'asset_status' => ['required', 'string', 'in:active,maintenance,retired,lost'],
        ]);

        DB::transaction(function () use ($maintenanceRow, $validated) {
            $now = now();

            DB::table('asset_maintenances')->where('id', $maintenanceRow->id)->update([
                'status' => 'completed',
                'completed_by' => auth()->id(),
                'completed_date' => $validated['completed_date'],
                'actual_cost' => $validated['actual_cost'] ?? 0,
                'resolution_notes' => $validated['resolution_notes'],
                'updated_at' => $now,
            ]);

            DB::table('asset_registers')->where('id', $maintenanceRow->asset_register_id)->update([
                'condition' => $validated['asset_condition'],
                'status' => $validated['asset_status'],
                'updated_at' => $now,
            ]);

            AuditLogger::log('asset_maintenance_completed', 'asset_maintenance', (int) $maintenanceRow->id, ['status' => $maintenanceRow->status], ['status' => 'completed'], (int) $maintenanceRow->company_id);
        });

        return redirect()->route('asset-maintenances.show', $maintenanceRow->id)->with('status', 'Maintenance berhasil diselesaikan.');
    }

    public function print(int $maintenance): View
    {
        $maintenanceRow = $this->findMaintenance($maintenance);
        $company = $this->company();
        $branch = DB::table('branches')->where('id', $maintenanceRow->branch_id)->first();

        return view('asset_maintenances.print', compact('maintenanceRow', 'company', 'branch'));
    }

    private function findAsset(int $asset): object
    {
        $company = $this->company();

        $assetRow = DB::table('asset_registers')
            ->join('items', 'items.id', '=', 'asset_registers.item_id')
            ->leftJoin('departments', 'departments.id', '=', 'asset_registers.department_id')
            ->leftJoin('storage_locations', 'storage_locations.id', '=', 'asset_registers.storage_location_id')
            ->where('asset_registers.company_id', $company->id)
            ->where('asset_registers.id', $asset)
            ->whereNull('asset_registers.deleted_at')
            ->select(
                'asset_registers.*',
                'items.sku',
                'items.name as item_name',
                'departments.code as department_code',
                'departments.name as department_name',
                'storage_locations.code as location_code',
                'storage_locations.name as location_name',
            )
            ->first();

        abort_unless($assetRow, 404);

        return $assetRow;
    }

    private function findMaintenance(int $maintenance): object
    {
        $company = $this->company();

        $maintenanceRow = DB::table('asset_maintenances')
            ->join('asset_registers', 'asset_registers.id', '=', 'asset_maintenances.asset_register_id')
            ->join('items', 'items.id', '=', 'asset_registers.item_id')
            ->join('users as requester', 'requester.id', '=', 'asset_maintenances.requested_by')
            ->leftJoin('users as completer', 'completer.id', '=', 'asset_maintenances.completed_by')
            ->leftJoin('departments', 'departments.id', '=', 'asset_registers.department_id')
            ->leftJoin('storage_locations', 'storage_locations.id', '=', 'asset_registers.storage_location_id')
            ->where('asset_maintenances.company_id', $company->id)
            ->where('asset_maintenances.id', $maintenance)
            ->whereNull('asset_maintenances.deleted_at')
            ->select(
                'asset_maintenances.*',
                'asset_registers.asset_number',
                'asset_registers.asset_name',
                'asset_registers.condition as asset_condition',
                'asset_registers.status as asset_status',
                'items.sku',
                'items.name as item_name',
                'requester.name as requester_name',
                'completer.name as completer_name',
                'departments.code as department_code',
                'departments.name as department_name',
                'storage_locations.code as location_code',
                'storage_locations.name as location_name',
            )
            ->first();

        abort_unless($maintenanceRow, 404);

        return $maintenanceRow;
    }

    private function nextDocumentNumber(int $companyId, int $branchId): string
    {
        $period = now()->format('Ym');
        $sequence = DB::table('document_sequences')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('document_type', 'asset_maintenance')
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            DB::table('document_sequences')->insert([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'document_type' => 'asset_maintenance',
                'prefix' => 'AM',
                'next_number' => 1,
                'padding' => 5,
                'period_format' => 'Ym',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('document_sequences')
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('document_type', 'asset_maintenance')
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

    private function statuses(): array
    {
        return ['open' => 'Open', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
    }

    private function priorities(): array
    {
        return ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
    }

    private function types(): array
    {
        return ['corrective' => 'Corrective', 'preventive' => 'Preventive', 'inspection' => 'Inspection', 'calibration' => 'Calibration'];
    }

    private function company(): object
    {
        return DB::table('companies')->where('is_active', true)->orderBy('id')->firstOrFail();
    }
}
