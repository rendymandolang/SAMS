<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockOnHandController extends Controller
{
    public function __invoke(): View
    {
        $company = DB::table('companies')->where('is_active', true)->orderBy('id')->firstOrFail();

        $rows = DB::table('stock_movements')
            ->join('items', 'items.id', '=', 'stock_movements.item_id')
            ->join('units', 'units.id', '=', 'items.base_unit_id')
            ->join('storage_locations', 'storage_locations.id', '=', 'stock_movements.storage_location_id')
            ->where('stock_movements.company_id', $company->id)
            ->select(
                'storage_locations.code as location_code',
                'storage_locations.name as location_name',
                'items.sku',
                'items.name as item_name',
                'units.code as unit_code',
                DB::raw('SUM(stock_movements.quantity) as quantity_on_hand'),
                DB::raw('SUM(stock_movements.total_cost) as stock_value'),
                DB::raw('CASE WHEN SUM(stock_movements.quantity) = 0 THEN 0 ELSE SUM(stock_movements.total_cost) / SUM(stock_movements.quantity) END as average_cost'),
                DB::raw('MAX(stock_movements.movement_at) as last_movement_at'),
            )
            ->groupBy(
                'storage_locations.code',
                'storage_locations.name',
                'items.sku',
                'items.name',
                'units.code',
            )
            ->havingRaw('SUM(stock_movements.quantity) <> 0')
            ->orderBy('storage_locations.code')
            ->orderBy('items.name')
            ->paginate(15);

        $summary = DB::query()
            ->fromSub(
                DB::table('stock_movements')
                    ->where('company_id', $company->id)
                    ->select(
                        'storage_location_id',
                        'item_id',
                        DB::raw('SUM(quantity) as quantity_on_hand'),
                        DB::raw('SUM(total_cost) as stock_value'),
                    )
                    ->groupBy('storage_location_id', 'item_id'),
                'stock_balances',
            )
            ->selectRaw('COUNT(*) as item_location_count, COALESCE(SUM(stock_value), 0) as total_stock_value')
            ->where('quantity_on_hand', '<>', 0)
            ->first();

        return view('inventory.stock_on_hand', compact('rows', 'summary'));
    }
}
