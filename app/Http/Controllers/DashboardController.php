<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $stats = [
            'purchase_requests' => DB::table('purchase_requests')->count(),
            'purchase_orders' => DB::table('purchase_orders')->count(),
            'items' => DB::table('items')->whereNull('deleted_at')->count(),
            'suppliers' => DB::table('suppliers')->whereNull('deleted_at')->count(),
            'stock_movements' => DB::table('stock_movements')->count(),
            'goods_receipts' => DB::table('goods_receipts')->count(),
            'stock_opnames' => DB::table('stock_opnames')->count(),
            'stock_on_hand_value' => DB::table('stock_movements')->sum('total_cost'),
            'budgets' => DB::table('budgets')->count(),
            'assets' => DB::table('asset_registers')->whereNull('deleted_at')->count(),
            'open_maintenance' => DB::table('asset_maintenances')->whereIn('status', ['open', 'in_progress'])->whereNull('deleted_at')->count(),
            'pending_approvals' => DB::table('purchase_requests')->where('status', 'submitted')->count()
                + DB::table('purchase_orders')->where('status', 'submitted')->count(),
        ];

        $company = DB::table('companies')->where('is_active', true)->orderBy('id')->first();
        $branch = DB::table('branches')->where('is_active', true)->orderBy('id')->first();

        $executive = [
            [
                'label' => 'Purchasing Flow',
                'value' => DB::table('purchase_orders')->whereIn('status', ['approved', 'partial_received', 'received'])->count(),
                'total' => max(1, DB::table('purchase_orders')->count()),
                'caption' => 'PO approved / receiving / completed',
            ],
            [
                'label' => 'Budget Usage',
                'value' => (float) DB::table('budget_lines')->selectRaw('COALESCE(SUM(committed_amount + actual_amount), 0) as total')->value('total'),
                'total' => max(1, (float) DB::table('budget_lines')->sum('allocated_amount')),
                'caption' => 'Committed + actual vs allocated',
            ],
            [
                'label' => 'Asset Health',
                'value' => DB::table('asset_registers')->where('status', 'active')->whereNull('deleted_at')->count(),
                'total' => max(1, DB::table('asset_registers')->whereNull('deleted_at')->count()),
                'caption' => 'Active assets vs total registered',
            ],
            [
                'label' => 'Maintenance Closure',
                'value' => DB::table('asset_maintenances')->where('status', 'completed')->whereNull('deleted_at')->count(),
                'total' => max(1, DB::table('asset_maintenances')->whereNull('deleted_at')->count()),
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
