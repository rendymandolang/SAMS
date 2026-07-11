<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportCenterController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();
        $company = DB::table('companies')->where('is_active', true)->orderBy('id')->first();

        $reports = collect($this->reports())
            ->filter(fn (array $report) => $user->hasAnyRole($report['roles']))
            ->values();

        return view('reports.index', [
            'reports' => $reports,
            'summary' => $this->summary((int) ($company->id ?? 0)),
        ]);
    }

    private function reports(): array
    {
        return [
            [
                'title' => 'Budget Control',
                'category' => 'Finance Control',
                'description' => 'Pantau allocated, committed, actual, remaining, dan risiko budget per department/account.',
                'route' => 'budget-control.index',
                'print_route' => 'budget-control.print',
                'export_route' => 'budget-control.export',
                'badge' => 'CSV + Print',
                'roles' => ['super_admin', 'finance', 'purchasing'],
            ],
            [
                'title' => 'Purchasing Cycle',
                'category' => 'Procurement',
                'description' => 'Tracking PR ke PO sampai GR, lengkap dengan status cycle, variance, dan receipt progress.',
                'route' => 'reports.purchasing.cycle',
                'print_route' => 'reports.purchasing.cycle.print',
                'export_route' => 'reports.purchasing.cycle.export',
                'badge' => 'CSV + Print',
                'roles' => ['super_admin', 'finance', 'purchasing'],
            ],
            [
                'title' => 'Supplier Performance',
                'category' => 'Procurement',
                'description' => 'Scorecard supplier berdasarkan nilai order, completion rate, reject rate, dan watch list.',
                'route' => 'reports.purchasing.suppliers',
                'print_route' => 'reports.purchasing.suppliers.print',
                'export_route' => 'reports.purchasing.suppliers.export',
                'badge' => 'CSV + Print',
                'roles' => ['super_admin', 'finance', 'purchasing'],
            ],
            [
                'title' => 'Laporan Mutasi Stok',
                'category' => 'Inventory Ledger',
                'description' => 'Ledger movement barang dengan opening, masuk, keluar, saldo berjalan, dan nilai movement.',
                'route' => 'reports.inventory.movements',
                'print_route' => null,
                'export_route' => 'reports.inventory.movements.export',
                'badge' => 'CSV + Browser Print',
                'roles' => ['super_admin', 'finance', 'purchasing', 'warehouse', 'staff'],
            ],
            [
                'title' => 'Asset Maintenance History',
                'category' => 'Asset Intelligence',
                'description' => 'Histori work order asset, biaya, overdue, ranking asset, dan status kontrol maintenance.',
                'route' => 'reports.assets.maintenance-history',
                'print_route' => 'reports.assets.maintenance-history.print',
                'export_route' => 'reports.assets.maintenance-history.export',
                'badge' => 'CSV + Print',
                'roles' => ['super_admin', 'finance', 'purchasing', 'warehouse'],
            ],
        ];
    }

    private function summary(int $companyId): array
    {
        if ($companyId === 0) {
            return [
                'ready_reports' => 0,
                'export_ready' => 0,
                'print_ready' => 0,
                'control_alerts' => 0,
            ];
        }

        $budgetAlerts = DB::table('budget_lines')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereRaw('(allocated_amount - committed_amount - actual_amount) < 0')
            ->count();

        $openMaintenance = DB::table('asset_maintenances')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereIn('status', ['open', 'in_progress'])
            ->count();

        $pendingPurchaseRequests = DB::table('purchase_requests')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('status', 'submitted')
            ->count();

        $pendingPurchaseOrders = DB::table('purchase_orders')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('status', 'submitted')
            ->count();

        return [
            'ready_reports' => 5,
            'export_ready' => 5,
            'print_ready' => 5,
            'control_alerts' => $budgetAlerts + $openMaintenance + $pendingPurchaseRequests + $pendingPurchaseOrders,
        ];
    }
}
