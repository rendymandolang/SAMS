<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AssetMaintenanceReportController extends Controller
{
    public function index(Request $request): View
    {
        return view('reports.asset_maintenance_history', $this->data($request));
    }

    public function print(Request $request): View
    {
        return view('reports.asset_maintenance_history_print', $this->data($request));
    }

    private function data(Request $request): array
    {
        $company = DB::table('companies')->where('is_active', true)->orderBy('id')->firstOrFail();
        $branch = DB::table('branches')->where('is_active', true)->orderBy('id')->first();

        $filters = [
            'date_from' => $request->input('date_from', now()->startOfMonth()->format('Y-m-d')),
            'date_to' => $request->input('date_to', now()->format('Y-m-d')),
            'status' => $request->input('status'),
            'asset_id' => $request->integer('asset_id') ?: null,
        ];

        $dateFrom = Carbon::parse($filters['date_from'])->startOfDay();
        $dateTo = Carbon::parse($filters['date_to'])->endOfDay();
        $today = now()->startOfDay();

        $rows = DB::table('asset_maintenances')
            ->join('asset_registers', 'asset_registers.id', '=', 'asset_maintenances.asset_register_id')
            ->join('items', 'items.id', '=', 'asset_registers.item_id')
            ->join('users as requester', 'requester.id', '=', 'asset_maintenances.requested_by')
            ->leftJoin('users as completer', 'completer.id', '=', 'asset_maintenances.completed_by')
            ->leftJoin('departments', 'departments.id', '=', 'asset_registers.department_id')
            ->leftJoin('storage_locations', 'storage_locations.id', '=', 'asset_registers.storage_location_id')
            ->where('asset_maintenances.company_id', $company->id)
            ->whereBetween('asset_maintenances.request_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($filters['status'], fn ($query, string $status) => $query->where('asset_maintenances.status', $status))
            ->when($filters['asset_id'], fn ($query, int $assetId) => $query->where('asset_maintenances.asset_register_id', $assetId))
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
            ->orderByDesc('asset_maintenances.request_date')
            ->orderByDesc('asset_maintenances.id')
            ->get()
            ->map(function (object $row) use ($today) {
                $requestDate = Carbon::parse($row->request_date)->startOfDay();
                $completedDate = $row->completed_date ? Carbon::parse($row->completed_date)->startOfDay() : null;
                $scheduledDate = $row->scheduled_date ? Carbon::parse($row->scheduled_date)->startOfDay() : null;

                $row->days_open = $completedDate
                    ? $requestDate->diffInDays($completedDate)
                    : $requestDate->diffInDays($today);
                $row->is_overdue = $row->status !== 'completed' && $scheduledDate && $scheduledDate->lt($today);
                $row->cost_variance = (float) $row->actual_cost - (float) $row->estimated_cost;
                $row->control_status = match (true) {
                    $row->is_overdue => 'overdue',
                    $row->status === 'completed' => 'completed',
                    $row->priority === 'urgent' || $row->priority === 'high' => 'watch',
                    default => 'normal',
                };

                return $row;
            });

        $summary = [
            'work_order_count' => $rows->count(),
            'open_count' => $rows->whereIn('status', ['open', 'in_progress'])->count(),
            'completed_count' => $rows->where('status', 'completed')->count(),
            'overdue_count' => $rows->where('is_overdue', true)->count(),
            'estimated_cost' => $rows->sum(fn (object $row) => (float) $row->estimated_cost),
            'actual_cost' => $rows->sum(fn (object $row) => (float) $row->actual_cost),
            'avg_days_open' => $rows->count() > 0 ? $rows->avg('days_open') : 0,
        ];

        $assetRankings = $rows
            ->groupBy('asset_register_id')
            ->map(function ($assetRows) {
                $first = $assetRows->first();

                return (object) [
                    'asset_id' => $first->asset_register_id,
                    'asset_number' => $first->asset_number,
                    'asset_name' => $first->asset_name,
                    'work_order_count' => $assetRows->count(),
                    'actual_cost' => $assetRows->sum(fn (object $row) => (float) $row->actual_cost),
                    'overdue_count' => $assetRows->where('is_overdue', true)->count(),
                    'latest_status' => $first->asset_status,
                    'latest_condition' => $first->asset_condition,
                ];
            })
            ->sortByDesc(fn (object $row) => [$row->actual_cost, $row->work_order_count])
            ->values()
            ->take(8);

        return [
            'company' => $company,
            'branch' => $branch,
            'filters' => $filters,
            'rows' => $rows,
            'summary' => $summary,
            'assetRankings' => $assetRankings,
            'assets' => $this->assets((int) $company->id),
            'statuses' => $this->statuses(),
        ];
    }

    private function assets(int $companyId)
    {
        return DB::table('asset_registers')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->orderBy('asset_number')
            ->get(['id', 'asset_number', 'asset_name']);
    }

    private function statuses(): array
    {
        return ['open' => 'Open', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
    }
}
