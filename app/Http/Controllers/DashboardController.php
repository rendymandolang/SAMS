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

        $stats = [
            'purchase_requests' => DB::table('purchase_requests')->where('company_id', $company->id)->count(),
            'purchase_orders' => DB::table('purchase_orders')->where('company_id', $company->id)->count(),
            'items' => DB::table('items')->where('company_id', $company->id)->whereNull('deleted_at')->count(),
            'suppliers' => DB::table('suppliers')->where('company_id', $company->id)->whereNull('deleted_at')->count(),
            'stock_movements' => DB::table('stock_movements')->where('company_id', $company->id)->count(),
            'goods_receipts' => DB::table('goods_receipts')->where('company_id', $company->id)->count(),
            'stock_opnames' => DB::table('stock_opnames')->where('company_id', $company->id)->count(),
            'stock_on_hand_value' => DB::table('stock_movements')->where('company_id', $company->id)->sum('total_cost'),
            'budgets' => DB::table('budgets')->where('company_id', $company->id)->count(),
            'assets' => DB::table('asset_registers')->where('company_id', $company->id)->whereNull('deleted_at')->count(),
            'open_maintenance' => DB::table('asset_maintenances')->where('company_id', $company->id)->whereIn('status', ['open', 'in_progress'])->whereNull('deleted_at')->count(),
            'pending_approvals' => DB::table('purchase_requests')->where('company_id', $company->id)->where('status', 'submitted')->count()
                + DB::table('purchase_orders')->where('company_id', $company->id)->where('status', 'submitted')->count(),
        ];

        $executive = [
            [
                'label' => 'Purchasing Flow',
                'value' => DB::table('purchase_orders')->where('company_id', $company->id)->whereIn('status', ['approved', 'partial_received', 'received'])->count(),
                'total' => max(1, DB::table('purchase_orders')->where('company_id', $company->id)->count()),
                'caption' => 'PO approved / receiving / completed',
            ],
            [
                'label' => 'Budget Usage',
                'value' => (float) DB::table('budget_lines')->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')->where('budgets.company_id', $company->id)->selectRaw('COALESCE(SUM(budget_lines.committed_amount + budget_lines.actual_amount), 0) as total')->value('total'),
                'total' => max(1, (float) DB::table('budget_lines')->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')->where('budgets.company_id', $company->id)->sum('budget_lines.allocated_amount')),
                'caption' => 'Committed + actual vs allocated',
            ],
            [
                'label' => 'Asset Health',
                'value' => DB::table('asset_registers')->where('company_id', $company->id)->where('status', 'active')->whereNull('deleted_at')->count(),
                'total' => max(1, DB::table('asset_registers')->where('company_id', $company->id)->whereNull('deleted_at')->count()),
                'caption' => 'Active assets vs total registered',
            ],
            [
                'label' => 'Maintenance Closure',
                'value' => DB::table('asset_maintenances')->where('company_id', $company->id)->where('status', 'completed')->whereNull('deleted_at')->count(),
                'total' => max(1, DB::table('asset_maintenances')->where('company_id', $company->id)->whereNull('deleted_at')->count()),
                'caption' => 'Completed work orders',
            ],
        ];

        $modules = [
            [
                'name' => 'Purchase Request',
                'description' => 'Permintaan pembelian dari tiap departemen sebelum menjadi PO. Draft, edit, budget check, submit, approve, dan reject sudah tersedia.',
                'status' => 'Aktif',
            ],
            [
                'name' => 'Purchase Order',
                'description' => 'Kontrol pesanan ke supplier dari PR approved. Draft, submit, dan approve PO sudah tersedia.',
                'status' => 'Aktif',
            ],
            [
                'name' => 'Inventory',
                'description' => 'Master item, gudang, Goods Receipt, Stock On Hand, dan Stock Opname adjustment sudah tersedia.',
                'status' => 'Aktif',
            ],
            [
                'name' => 'Budget Control',
                'description' => 'Anggaran departemen, komitmen, aktual, dan validasi belanja awal pada PR.',
                'status' => 'Fondasi siap',
            ],
            [
                'name' => 'Approval Flow',
                'description' => 'Approval awal Purchase Request sudah aktif, dengan jejak approve/reject.',
                'status' => 'Aktif',
            ],
            [
                'name' => 'Audit Trail',
                'description' => 'Jejak perubahan data untuk keamanan dan akuntabilitas.',
                'status' => 'Berikutnya',
            ],
        ];

        return view('dashboard.index', compact('stats', 'company', 'branch', 'modules', 'executive'));
    }
}
