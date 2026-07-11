<?php

namespace App\Http\Controllers;

use App\Support\CompanyContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportCenterController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();
        $company = app(CompanyContext::class)->current();

        $reports = collect($this->reports())
            ->filter(fn (array $report) => $user->canAccessModule($report['module'])
                && $user->hasPermission($report['permission']))
            ->values();
        $summary = $this->summary((int) $company->id, $user);
        $summary['ready_reports'] = $reports->count();
        $summary['export_ready'] = $reports->count();
        $summary['print_ready'] = $reports->count();

        return view('reports.index', [
            'reports' => $reports,
            'summary' => $summary,
        ]);
    }

    private function reports(): array
    {
        return [
            [
                'name_key' => 'budget_control',
                'category_key' => 'finance',
                'route' => 'budget-control.index',
                'print_route' => 'budget-control.print',
                'export_route' => 'budget-control.export',
                'badge' => 'CSV + Print',
                'module' => 'budgeting',
                'permission' => 'budgeting.view',
            ],
            [
                'name_key' => 'purchasing_cycle',
                'category_key' => 'procurement',
                'route' => 'reports.purchasing.cycle',
                'print_route' => 'reports.purchasing.cycle.print',
                'export_route' => 'reports.purchasing.cycle.export',
                'badge' => 'CSV + Print',
                'module' => 'procurement',
                'permission' => 'reporting.procurement.view',
            ],
            [
                'name_key' => 'supplier_performance',
                'category_key' => 'procurement',
                'route' => 'reports.purchasing.suppliers',
                'print_route' => 'reports.purchasing.suppliers.print',
                'export_route' => 'reports.purchasing.suppliers.export',
                'badge' => 'CSV + Print',
                'module' => 'procurement',
                'permission' => 'reporting.procurement.view',
            ],
            [
                'name_key' => 'inventory_movement',
                'category_key' => 'inventory',
                'route' => 'reports.inventory.movements',
                'print_route' => null,
                'export_route' => 'reports.inventory.movements.export',
                'badge' => 'CSV + Browser Print',
                'module' => 'inventory',
                'permission' => 'reporting.view',
            ],
            [
                'name_key' => 'asset_maintenance_history',
                'category_key' => 'assets',
                'route' => 'reports.assets.maintenance-history',
                'print_route' => 'reports.assets.maintenance-history.print',
                'export_route' => 'reports.assets.maintenance-history.export',
                'badge' => 'CSV + Print',
                'module' => 'assets',
                'permission' => 'reporting.assets.view',
            ],
        ];
    }

    private function summary(int $companyId, User $user): array
    {
        if ($companyId === 0) {
            return [
                'ready_reports' => 0,
                'export_ready' => 0,
                'print_ready' => 0,
                'control_alerts' => 0,
            ];
        }

        $budgetAlerts = $user->canAccessModule('budgeting') && $user->hasPermission('budgeting.view')
            ? DB::table('budget_lines')
                ->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')
                ->where('budgets.company_id', $companyId)
                ->whereRaw('(allocated_amount - committed_amount - actual_amount) < 0')
                ->count()
            : 0;

        $openMaintenance = $user->canAccessModule('assets') && $user->hasPermission('reporting.assets.view')
            ? DB::table('asset_maintenances')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->whereIn('status', ['open', 'in_progress'])
                ->count()
            : 0;

        $canViewProcurement = $user->canAccessModule('procurement')
            && $user->hasPermission('reporting.procurement.view');
        $pendingPurchaseRequests = $canViewProcurement
            ? DB::table('purchase_requests')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->where('status', 'submitted')
                ->count()
            : 0;
        $pendingPurchaseOrders = $canViewProcurement
            ? DB::table('purchase_orders')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->where('status', 'submitted')
                ->count()
            : 0;

        return [
            'ready_reports' => 5,
            'export_ready' => 5,
            'print_ready' => 5,
            'control_alerts' => $budgetAlerts + $openMaintenance + $pendingPurchaseRequests + $pendingPurchaseOrders,
        ];
    }
}
