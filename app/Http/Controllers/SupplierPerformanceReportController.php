<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupplierPerformanceReportController extends Controller
{
    public function index(Request $request): View
    {
        return view('reports.supplier_performance', $this->data($request));
    }

    public function print(Request $request): View
    {
        return view('reports.supplier_performance_print', $this->data($request));
    }

    private function data(Request $request): array
    {
        $company = DB::table('companies')->where('is_active', true)->orderBy('id')->firstOrFail();
        $branch = DB::table('branches')->where('is_active', true)->orderBy('id')->first();

        $filters = [
            'date_from' => $request->input('date_from', now()->startOfMonth()->format('Y-m-d')),
            'date_to' => $request->input('date_to', now()->format('Y-m-d')),
            'supplier_id' => $request->integer('supplier_id') ?: null,
        ];

        $dateFrom = Carbon::parse($filters['date_from'])->startOfDay();
        $dateTo = Carbon::parse($filters['date_to'])->endOfDay();

        $purchaseOrders = DB::table('purchase_orders')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->where('purchase_orders.company_id', $company->id)
            ->whereBetween('purchase_orders.order_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($filters['supplier_id'], fn ($query, int $supplierId) => $query->where('purchase_orders.supplier_id', $supplierId))
            ->whereNull('purchase_orders.deleted_at')
            ->select(
                'purchase_orders.*',
                'suppliers.code as supplier_code',
                'suppliers.name as supplier_name',
                'suppliers.contact_person',
            )
            ->get();

        $purchaseOrderIds = $purchaseOrders->pluck('id')->all();
        $receiptRows = collect();

        if ($purchaseOrderIds !== []) {
            $receiptRows = DB::table('goods_receipt_items')
                ->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')
                ->join('purchase_order_items', 'purchase_order_items.id', '=', 'goods_receipt_items.purchase_order_item_id')
                ->whereIn('goods_receipts.purchase_order_id', $purchaseOrderIds)
                ->select(
                    'goods_receipts.purchase_order_id',
                    'goods_receipts.status as goods_receipt_status',
                    'goods_receipt_items.accepted_quantity',
                    'goods_receipt_items.rejected_quantity',
                    'goods_receipt_items.unit_cost',
                    'purchase_order_items.quantity as ordered_quantity',
                )
                ->get();
        }

        $receiptByPurchaseOrder = $receiptRows->groupBy('purchase_order_id');

        $rows = $purchaseOrders
            ->groupBy('supplier_id')
            ->map(function ($orders) use ($receiptByPurchaseOrder) {
                $first = $orders->first();
                $orderIds = $orders->pluck('id');
                $receiptRows = $orderIds->flatMap(fn (int $id) => $receiptByPurchaseOrder->get($id, collect()));
                $totalOrderedQuantity = $orders->sum(function (object $order) use ($receiptByPurchaseOrder) {
                    $rows = $receiptByPurchaseOrder->get($order->id, collect());

                    return $rows->isNotEmpty()
                        ? $rows->sum(fn (object $row) => (float) $row->ordered_quantity)
                        : 0;
                });
                $acceptedQuantity = $receiptRows->sum(fn (object $row) => (float) $row->accepted_quantity);
                $rejectedQuantity = $receiptRows->sum(fn (object $row) => (float) $row->rejected_quantity);
                $receivedQuantity = $acceptedQuantity + $rejectedQuantity;
                $completionRate = $totalOrderedQuantity > 0 ? min(100, ($acceptedQuantity / $totalOrderedQuantity) * 100) : 0;
                $rejectionRate = $receivedQuantity > 0 ? min(100, ($rejectedQuantity / $receivedQuantity) * 100) : 0;
                $completedOrders = $orders->where('status', 'received')->count();
                $activeOrders = $orders->whereIn('status', ['approved', 'partial_received'])->count();

                return (object) [
                    'supplier_id' => $first->supplier_id,
                    'supplier_code' => $first->supplier_code,
                    'supplier_name' => $first->supplier_name,
                    'contact_person' => $first->contact_person,
                    'purchase_order_count' => $orders->count(),
                    'completed_order_count' => $completedOrders,
                    'active_order_count' => $activeOrders,
                    'total_order_amount' => $orders->sum(fn (object $order) => (float) $order->total_amount),
                    'accepted_value' => $receiptRows->sum(fn (object $row) => (float) $row->accepted_quantity * (float) $row->unit_cost),
                    'ordered_quantity' => $totalOrderedQuantity,
                    'accepted_quantity' => $acceptedQuantity,
                    'rejected_quantity' => $rejectedQuantity,
                    'completion_rate' => $completionRate,
                    'rejection_rate' => $rejectionRate,
                    'performance_status' => $this->performanceStatus($completionRate, $rejectionRate, $activeOrders),
                ];
            })
            ->sortByDesc('total_order_amount')
            ->values();

        $summary = [
            'supplier_count' => $rows->count(),
            'purchase_order_count' => $purchaseOrders->count(),
            'total_order_amount' => $rows->sum(fn (object $row) => (float) $row->total_order_amount),
            'accepted_value' => $rows->sum(fn (object $row) => (float) $row->accepted_value),
            'watch_count' => $rows->whereIn('performance_status', ['watch', 'risk'])->count(),
        ];

        return [
            'company' => $company,
            'branch' => $branch,
            'filters' => $filters,
            'suppliers' => $this->suppliers((int) $company->id),
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    private function performanceStatus(float $completionRate, float $rejectionRate, int $activeOrders): string
    {
        if ($rejectionRate >= 10) {
            return 'risk';
        }

        if ($rejectionRate > 0 || $activeOrders > 0 || ($completionRate > 0 && $completionRate < 100)) {
            return 'watch';
        }

        return $completionRate >= 100 ? 'excellent' : 'new';
    }

    private function suppliers(int $companyId)
    {
        return DB::table('suppliers')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }
}
