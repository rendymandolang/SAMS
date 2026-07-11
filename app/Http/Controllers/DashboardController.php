<?php

namespace App\Http\Controllers;

use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $context = app(CompanyContext::class);
        $company = $context->current();
        $branch = $context->branch();
        $user = auth()->user();

        $access = [
            'master' => $user->hasPermission('core.master.view'),
            'pr_view' => $user->canAccessModule('procurement') && $user->hasPermission('procurement.pr.view'),
            'pr_manage' => $user->canAccessModule('procurement') && $user->hasPermission('procurement.pr.manage'),
            'po_view' => $user->canAccessModule('procurement') && $user->hasPermission('procurement.po.view'),
            'gr_view' => $user->canAccessModule('inventory') && $user->hasPermission('inventory.gr.view'),
            'stock_view' => $user->canAccessModule('inventory') && $user->hasPermission('inventory.stock.view'),
            'stock_manage' => $user->canAccessModule('inventory') && $user->hasPermission('inventory.stock.manage'),
            'asset_view' => $user->canAccessModule('assets') && $user->hasPermission('assets.register.view'),
            'maintenance_view' => $user->canAccessModule('assets') && $user->hasPermission('assets.maintenance.view'),
            'budget' => $user->canAccessModule('budgeting') && $user->hasPermission('budgeting.view'),
            'inventory_report' => $user->canAccessModule('reporting') && $user->canAccessModule('inventory') && $user->hasPermission('reporting.view'),
            'procurement_report' => $user->canAccessModule('reporting') && $user->canAccessModule('procurement') && $user->hasPermission('reporting.procurement.view'),
            'asset_report' => $user->canAccessModule('reporting') && $user->canAccessModule('assets') && $user->hasPermission('reporting.assets.view'),
            'approval_center' => $user->canAccessModule('procurement')
                && $user->hasPermission('core.approvals.view')
                && ($user->hasPermission('procurement.pr.approve') || $user->hasPermission('procurement.po.approve')),
            'audit' => $user->hasPermission('core.audit.view'),
        ];

        $stats = [];
        if ($access['pr_view']) {
            $stats['purchase_requests'] = DB::table('purchase_requests')->where('company_id', $company->id)->count();
        }
        if ($access['po_view']) {
            $stats['purchase_orders'] = DB::table('purchase_orders')->where('company_id', $company->id)->count();
        }
        if ($access['master']) {
            $stats['items'] = DB::table('items')->where('company_id', $company->id)->whereNull('deleted_at')->count();
            $stats['suppliers'] = DB::table('suppliers')->where('company_id', $company->id)->whereNull('deleted_at')->count();
        }
        if ($access['gr_view']) {
            $stats['goods_receipts'] = DB::table('goods_receipts')->where('company_id', $company->id)->count();
        }
        if ($access['stock_view']) {
            $stats['stock_opnames'] = DB::table('stock_opnames')->where('company_id', $company->id)->count();
            $stats['stock_on_hand_value'] = DB::table('stock_movements')->where('company_id', $company->id)->sum('total_cost');
        }
        if ($access['asset_view']) {
            $stats['assets'] = DB::table('asset_registers')->where('company_id', $company->id)->whereNull('deleted_at')->count();
        }
        if ($access['maintenance_view']) {
            $stats['open_maintenance'] = DB::table('asset_maintenances')->where('company_id', $company->id)->whereIn('status', ['open', 'in_progress'])->whereNull('deleted_at')->count();
        }
        if ($access['approval_center']) {
            $stats['pending_approvals'] = ($user->hasPermission('procurement.pr.approve')
                ? DB::table('purchase_requests')->where('company_id', $company->id)->where('status', 'submitted')->count()
                : 0) + ($user->hasPermission('procurement.po.approve')
                ? DB::table('purchase_orders')->where('company_id', $company->id)->where('status', 'submitted')->count()
                : 0);
        }

        $executive = [];
        if ($access['po_view']) {
            $executive[] = [
                'label' => 'Purchasing Flow',
                'value' => DB::table('purchase_orders')->where('company_id', $company->id)->whereIn('status', ['approved', 'partial_received', 'received'])->count(),
                'total' => max(1, DB::table('purchase_orders')->where('company_id', $company->id)->count()),
                'caption' => 'PO approved / receiving / completed',
            ];
        }
        if ($access['budget']) {
            $executive[] = [
                'label' => 'Budget Usage',
                'value' => (float) DB::table('budget_lines')->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')->where('budgets.company_id', $company->id)->selectRaw('COALESCE(SUM(budget_lines.committed_amount + budget_lines.actual_amount), 0) as total')->value('total'),
                'total' => max(1, (float) DB::table('budget_lines')->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')->where('budgets.company_id', $company->id)->sum('budget_lines.allocated_amount')),
                'caption' => 'Committed + actual vs allocated',
            ];
        }
        if ($access['asset_view']) {
            $executive[] = [
                'label' => 'Asset Health',
                'value' => DB::table('asset_registers')->where('company_id', $company->id)->where('status', 'active')->whereNull('deleted_at')->count(),
                'total' => max(1, DB::table('asset_registers')->where('company_id', $company->id)->whereNull('deleted_at')->count()),
                'caption' => 'Active assets vs total registered',
            ];
        }
        if ($access['maintenance_view']) {
            $executive[] = [
                'label' => 'Maintenance Closure',
                'value' => DB::table('asset_maintenances')->where('company_id', $company->id)->where('status', 'completed')->whereNull('deleted_at')->count(),
                'total' => max(1, DB::table('asset_maintenances')->where('company_id', $company->id)->whereNull('deleted_at')->count()),
                'caption' => 'Completed work orders',
            ];
        }

        $modules = [
            [
                'name' => 'Purchase Request',
                'description' => 'Permintaan pembelian dari tiap departemen sebelum menjadi PO. Draft, edit, budget check, submit, approve, dan reject sudah tersedia.',
                'status' => 'Aktif',
                'visible' => $access['pr_view'],
            ],
            [
                'name' => 'Purchase Order',
                'description' => 'Kontrol pesanan ke supplier dari PR approved. Draft, submit, dan approve PO sudah tersedia.',
                'status' => 'Aktif',
                'visible' => $access['po_view'],
            ],
            [
                'name' => 'Inventory',
                'description' => 'Master item, gudang, Goods Receipt, Stock On Hand, dan Stock Opname adjustment sudah tersedia.',
                'status' => 'Aktif',
                'visible' => $access['gr_view'] || $access['stock_view'],
            ],
            [
                'name' => 'Budget Control',
                'description' => 'Anggaran departemen, komitmen, aktual, dan validasi belanja awal pada PR.',
                'status' => 'Fondasi siap',
                'visible' => $access['budget'],
            ],
            [
                'name' => 'Approval Flow',
                'description' => 'Approval awal Purchase Request sudah aktif, dengan jejak approve/reject.',
                'status' => 'Aktif',
                'visible' => $access['approval_center'],
            ],
            [
                'name' => 'Audit Trail',
                'description' => 'Jejak perubahan data untuk keamanan dan akuntabilitas.',
                'status' => 'Aktif',
                'visible' => $access['audit'],
            ],
        ];
        $modules = collect($modules)->where('visible', true)->values();

        return view('dashboard.index', compact('stats', 'company', 'branch', 'modules', 'executive', 'access'));
    }
}
