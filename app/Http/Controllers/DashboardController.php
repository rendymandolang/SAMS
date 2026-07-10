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
            'budgets' => DB::table('budgets')->count(),
        ];

        $company = DB::table('companies')->where('is_active', true)->orderBy('id')->first();
        $branch = DB::table('branches')->where('is_active', true)->orderBy('id')->first();

        $modules = [
            [
                'name' => 'Purchase Request',
                'description' => 'Permintaan pembelian dari tiap departemen sebelum menjadi PO. Draft, edit, budget check, submit, approve, dan reject sudah tersedia.',
                'status' => 'Aktif',
            ],
            [
                'name' => 'Purchase Order',
                'description' => 'Kontrol pesanan ke supplier, nilai transaksi, dan status penerimaan.',
                'status' => 'Fondasi siap',
            ],
            [
                'name' => 'Inventory',
                'description' => 'Master item, satuan, gudang, dan pergerakan stok.',
                'status' => 'Fondasi siap',
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

        return view('dashboard.index', compact('stats', 'company', 'branch', 'modules'));
    }
}
