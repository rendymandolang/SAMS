<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventoryMovementReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        $company = DB::table('companies')->where('is_active', true)->orderBy('id')->firstOrFail();

        $filters = [
            'date_from' => $request->input('date_from', now()->startOfMonth()->format('Y-m-d')),
            'date_to' => $request->input('date_to', now()->format('Y-m-d')),
            'storage_location_id' => $request->integer('storage_location_id') ?: null,
            'item_id' => $request->integer('item_id') ?: null,
        ];

        $dateFrom = Carbon::parse($filters['date_from'])->startOfDay();
        $dateTo = Carbon::parse($filters['date_to'])->endOfDay();

        $openingQuantity = $this->openingQuantity((int) $company->id, $dateFrom, $filters);
        $openingValue = $this->openingValue((int) $company->id, $dateFrom, $filters);

        $movements = $this->movements((int) $company->id, $dateFrom, $dateTo, $filters);
        $runningQuantity = $openingQuantity;
        $runningValue = $openingValue;

        $rows = $movements->map(function (object $movement) use (&$runningQuantity, &$runningValue) {
            $quantity = (float) $movement->quantity;
            $value = (float) $movement->total_cost;

            $runningQuantity += $quantity;
            $runningValue += $value;

            $movement->quantity_in = $quantity > 0 ? $quantity : 0;
            $movement->quantity_out = $quantity < 0 ? abs($quantity) : 0;
            $movement->running_quantity = $runningQuantity;
            $movement->running_value = $runningValue;

            return $movement;
        });

        $summary = [
            'opening_quantity' => $openingQuantity,
            'opening_value' => $openingValue,
            'quantity_in' => $rows->sum('quantity_in'),
            'quantity_out' => $rows->sum('quantity_out'),
            'closing_quantity' => $runningQuantity,
            'closing_value' => $runningValue,
            'movement_count' => $rows->count(),
        ];

        return view('reports.inventory_movements', [
            'filters' => $filters,
            'items' => $this->items((int) $company->id),
            'locations' => $this->locations((int) $company->id),
            'rows' => $rows,
            'summary' => $summary,
        ]);
    }

    private function movements(int $companyId, Carbon $dateFrom, Carbon $dateTo, array $filters)
    {
        return $this->baseMovementQuery($companyId, $filters)
            ->whereBetween('stock_movements.movement_at', [$dateFrom, $dateTo])
            ->select(
                'stock_movements.*',
                'items.sku',
                'items.name as item_name',
                'units.code as unit_code',
                'storage_locations.code as location_code',
                'storage_locations.name as location_name',
            )
            ->orderBy('stock_movements.movement_at')
            ->orderBy('stock_movements.id')
            ->get();
    }

    private function openingQuantity(int $companyId, Carbon $dateFrom, array $filters): float
    {
        return (float) $this->baseMovementQuery($companyId, $filters)
            ->where('stock_movements.movement_at', '<', $dateFrom)
            ->sum('stock_movements.quantity');
    }

    private function openingValue(int $companyId, Carbon $dateFrom, array $filters): float
    {
        return (float) $this->baseMovementQuery($companyId, $filters)
            ->where('stock_movements.movement_at', '<', $dateFrom)
            ->sum('stock_movements.total_cost');
    }

    private function baseMovementQuery(int $companyId, array $filters)
    {
        return DB::table('stock_movements')
            ->join('items', 'items.id', '=', 'stock_movements.item_id')
            ->join('units', 'units.id', '=', 'items.base_unit_id')
            ->join('storage_locations', 'storage_locations.id', '=', 'stock_movements.storage_location_id')
            ->where('stock_movements.company_id', $companyId)
            ->when($filters['storage_location_id'], fn ($query, int $locationId) => $query->where('stock_movements.storage_location_id', $locationId))
            ->when($filters['item_id'], fn ($query, int $itemId) => $query->where('stock_movements.item_id', $itemId));
    }

    private function items(int $companyId)
    {
        return DB::table('items')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'sku', 'name']);
    }

    private function locations(int $companyId)
    {
        return DB::table('storage_locations')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }
}
