<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AiPredictiveAnalytics
{
    public function stockForecasts(int $companyId): array
    {
        return DB::table('items')->leftJoin('stock_movements', 'stock_movements.item_id', '=', 'items.id')
            ->where('items.company_id', $companyId)->where('items.item_type', 'inventory')->whereNull('items.deleted_at')
            ->groupBy('items.id', 'items.sku', 'items.name', 'items.minimum_stock', 'items.maximum_stock')
            ->select('items.id', 'items.sku', 'items.name', 'items.minimum_stock', 'items.maximum_stock')
            ->selectRaw('COALESCE(SUM(stock_movements.quantity), 0) current_stock')
            ->selectRaw('COALESCE(SUM(CASE WHEN stock_movements.movement_at >= ? AND stock_movements.quantity < 0 THEN -stock_movements.quantity ELSE 0 END), 0) consumption_90d', [now()->subDays(90)])
            ->get()->map(function (object $row): array {
                $daily = (float) $row->consumption_90d / 90;
                $stock = (float) $row->current_stock;
                $daysCover = $daily > 0 ? round(max(0, $stock) / $daily, 1) : null;
                $target = max((float) $row->minimum_stock, $daily * 30);
                if ($row->maximum_stock !== null) {
                    $target = min($target, (float) $row->maximum_stock);
                }

                return ['item_id' => (int) $row->id, 'sku' => $row->sku, 'name' => $row->name, 'current_stock' => $stock, 'average_daily_consumption' => round($daily, 4), 'days_cover' => $daysCover, 'recommended_reorder' => round(max(0, $target - $stock), 2), 'confidence' => $daily > 0 ? 'medium' : 'insufficient_data'];
            })->filter(fn (array $row) => $row['recommended_reorder'] > 0 || $row['average_daily_consumption'] > 0)
            ->sortBy(fn (array $row) => $row['days_cover'] ?? PHP_FLOAT_MAX)->take(10)->values()->all();
    }

    public function priceAnomalies(int $companyId): array
    {
        $rows = DB::table('purchase_order_items')->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->join('items', 'items.id', '=', 'purchase_order_items.item_id')
            ->where('purchase_orders.company_id', $companyId)->whereNull('purchase_orders.deleted_at')
            ->whereIn('purchase_orders.status', ['approved', 'partial_received', 'received'])
            ->select('purchase_order_items.item_id', 'purchase_order_items.unit_price', 'purchase_orders.document_number', 'purchase_orders.order_date', 'items.sku', 'items.name')
            ->orderBy('purchase_orders.order_date')->orderBy('purchase_order_items.id')->get();

        return $rows->groupBy('item_id')->map(function (Collection $history): ?array {
            if ($history->count() < 2) {
                return null;
            }
            $latest = $history->last();
            $baseline = $history->slice(0, -1)->avg(fn (object $row) => (float) $row->unit_price);
            if ($baseline <= 0) {
                return null;
            }
            $deviation = (((float) $latest->unit_price - $baseline) / $baseline) * 100;
            if (abs($deviation) < 15) {
                return null;
            }

            return ['item_id' => (int) $latest->item_id, 'sku' => $latest->sku, 'name' => $latest->name, 'document_number' => $latest->document_number, 'latest_price' => (float) $latest->unit_price, 'historical_average' => round($baseline, 2), 'deviation_percent' => round($deviation, 1), 'severity' => abs($deviation) >= 30 ? 'critical' : 'warning'];
        })->filter()->sortByDesc(fn (array $row) => abs($row['deviation_percent']))->take(10)->values()->all();
    }

    public function supplierRisks(int $companyId): array
    {
        $suppliers = DB::table('suppliers')->where('company_id', $companyId)->where('is_active', true)->whereNull('deleted_at')->get(['id', 'code', 'name']);

        return $suppliers->map(function (object $supplier): ?array {
            $orders = DB::table('purchase_orders')->where('supplier_id', $supplier->id)->whereNull('deleted_at')->get();
            if ($orders->isEmpty()) {
                return null;
            }
            $orderIds = $orders->pluck('id');
            $receipts = DB::table('goods_receipts')->whereIn('purchase_order_id', $orderIds)->whereIn('status', ['posted', 'reversed'])->get()->groupBy('purchase_order_id');
            $late = $orders->filter(function (object $order) use ($receipts): bool {
                if (! $order->expected_date) {
                    return false;
                }
                $first = $receipts->get($order->id)?->sortBy('received_at')->first();

                return $first ? Carbon::parse($first->received_at)->toDateString() > $order->expected_date : Carbon::parse($order->expected_date)->isPast();
            })->count();
            $quantities = DB::table('goods_receipt_items')->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')->whereIn('goods_receipts.purchase_order_id', $orderIds)->where('goods_receipts.status', 'posted')->selectRaw('COALESCE(SUM(accepted_quantity),0) accepted, COALESCE(SUM(rejected_quantity),0) rejected')->first();
            $totalQty = (float) $quantities->accepted + (float) $quantities->rejected;
            $rejectRate = $totalQty > 0 ? (float) $quantities->rejected / $totalQty : 0;
            $incomplete = $orders->whereNotIn('status', ['received'])->count();
            $risk = min(100, round(($late / $orders->count()) * 40 + $rejectRate * 40 + ($incomplete / $orders->count()) * 20));

            return ['supplier_id' => (int) $supplier->id, 'code' => $supplier->code, 'name' => $supplier->name, 'order_count' => $orders->count(), 'late_orders' => $late, 'reject_rate_percent' => round($rejectRate * 100, 1), 'incomplete_orders' => $incomplete, 'risk_score' => $risk, 'risk_level' => $risk >= 70 ? 'high' : ($risk >= 40 ? 'medium' : 'low')];
        })->filter()->sortByDesc('risk_score')->take(10)->values()->all();
    }

    public function maintenancePredictions(int $companyId): array
    {
        $assets = DB::table('asset_registers')->where('company_id', $companyId)->where('status', 'active')->whereNull('deleted_at')->get(['id', 'asset_number', 'asset_name', 'condition', 'acquisition_date']);

        return $assets->map(function (object $asset): array {
            $history = DB::table('asset_maintenances')->where('asset_register_id', $asset->id)->whereNull('deleted_at')->orderBy('completed_date')->get();
            $completed = $history->where('status', 'completed')->filter(fn (object $row) => $row->completed_date);
            $lastCompleted = $completed->last()?->completed_date;
            $intervals = $completed->pluck('completed_date')->map(fn ($date) => Carbon::parse($date))->values()->map(fn (Carbon $date, int $index) => $index === 0 ? null : $completed->pluck('completed_date')->map(fn ($d) => Carbon::parse($d))->values()[$index - 1]->diffInDays($date))->filter();
            $interval = $intervals->isNotEmpty() ? max(30, (int) round($intervals->avg())) : 180;
            $baseDate = $lastCompleted ? Carbon::parse($lastCompleted) : Carbon::parse($asset->acquisition_date);
            $predicted = $baseDate->copy()->addDays($interval);
            $overdue = $history->whereIn('status', ['open', 'in_progress'])->filter(fn (object $row) => $row->scheduled_date && Carbon::parse($row->scheduled_date)->isPast())->count();
            $recentEvents = $history->filter(fn (object $row) => Carbon::parse($row->request_date)->gte(now()->subYear()))->count();
            $conditionRisk = ['poor' => 45, 'fair' => 25, 'good' => 5][$asset->condition] ?? 15;
            $score = min(100, $conditionRisk + min(30, $recentEvents * 8) + min(25, $overdue * 15) + ($predicted->isPast() ? 15 : 0));

            return ['asset_id' => (int) $asset->id, 'asset_number' => $asset->asset_number, 'asset_name' => $asset->asset_name, 'condition' => $asset->condition, 'maintenance_events_12m' => $recentEvents, 'overdue_work_orders' => $overdue, 'predicted_maintenance_date' => $predicted->toDateString(), 'risk_score' => $score, 'risk_level' => $score >= 70 ? 'high' : ($score >= 40 ? 'medium' : 'low'), 'confidence' => $completed->count() >= 2 ? 'high' : ($completed->count() === 1 ? 'medium' : 'low')];
        })->sortByDesc('risk_score')->take(10)->values()->all();
    }
}
