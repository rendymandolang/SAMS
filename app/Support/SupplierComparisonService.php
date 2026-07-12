<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class SupplierComparisonService
{
    public function compare(int $companyId, string $query, float $quantity, string $unit, ?float $budget): array
    {
        $unit = strtoupper($unit);
        $tokens = array_values(array_filter(preg_split('/\s+/u', mb_strtolower(trim($query)))));
        $rows = DB::table('supplier_catalog_items')
            ->join('supplier_catalogs', 'supplier_catalogs.id', '=', 'supplier_catalog_items.supplier_catalog_id')
            ->join('suppliers', 'suppliers.id', '=', 'supplier_catalogs.supplier_id')
            ->where('supplier_catalogs.company_id', $companyId)
            ->where('supplier_catalogs.status', 'published')
            ->where('supplier_catalog_items.status', 'published')
            ->where('supplier_catalog_items.normalized_unit', $unit)
            ->where(function ($queryBuilder) use ($tokens) {
                foreach ($tokens as $token) {
                    $queryBuilder->where(function ($subQuery) use ($token) {
                        $subQuery->whereRaw('LOWER(supplier_catalog_items.source_name) LIKE ?', ['%'.$token.'%'])
                            ->orWhereRaw("LOWER(COALESCE(supplier_catalog_items.brand, '')) LIKE ?", ['%'.$token.'%'])
                            ->orWhereRaw("LOWER(COALESCE(supplier_catalog_items.category, '')) LIKE ?", ['%'.$token.'%']);
                    });
                }
            })
            ->where(fn ($queryBuilder) => $queryBuilder->whereNull('supplier_catalogs.valid_from')->orWhereDate('supplier_catalogs.valid_from', '<=', today()))
            ->where(fn ($queryBuilder) => $queryBuilder->whereNull('supplier_catalogs.valid_until')->orWhereDate('supplier_catalogs.valid_until', '>=', today()))
            ->select('supplier_catalog_items.*', 'supplier_catalogs.currency', 'supplier_catalogs.name as catalog_name', 'suppliers.id as supplier_id', 'suppliers.name as supplier_name')
            ->limit(100)
            ->get();

        $risk = collect(app(AiPredictiveAnalytics::class)->supplierRisks($companyId))->keyBy('supplier_id');
        $results = $rows->map(function (object $row) use ($quantity, $budget, $risk): array {
            $total = (float) $row->normalized_unit_price * $quantity;
            $supplierRisk = $risk->get((int) $row->supplier_id)['risk_score'] ?? 0;
            $score = round(100 - min(40, $supplierRisk * .4) - min(20, (1 - (float) $row->confidence) * 20), 1);

            return [
                'catalog_item_id' => (int) $row->id,
                'supplier_id' => (int) $row->supplier_id,
                'supplier_name' => $row->supplier_name,
                'product_name' => $row->source_name,
                'brand' => $row->brand,
                'catalog_name' => $row->catalog_name,
                'currency' => $row->currency,
                'unit_price' => (float) $row->normalized_unit_price,
                'quantity' => $quantity,
                'unit' => $row->normalized_unit,
                'total_cost' => round($total, 2),
                'budget_variance' => $budget === null ? null : round($budget - $total, 2),
                'within_budget' => $budget === null ? null : $total <= $budget,
                'supplier_risk' => $supplierRisk,
                'confidence' => (float) $row->confidence,
                'recommendation_score' => $score,
            ];
        })->sortBy([['within_budget', 'desc'], ['total_cost', 'asc'], ['recommendation_score', 'desc']])->values()->all();

        $costs = collect($results)->pluck('total_cost');
        $recommended = $results[0] ?? null;
        $summary = [
            'result_count' => count($results),
            'recommended_supplier' => $recommended['supplier_name'] ?? null,
            'recommended_catalog_item_id' => $recommended['catalog_item_id'] ?? null,
            'recommended_total' => $recommended['total_cost'] ?? null,
            'average_total' => $costs->isEmpty() ? null : round((float) $costs->average(), 2),
            'potential_savings' => $costs->count() < 2 ? 0 : round((float) $costs->max() - (float) $costs->min(), 2),
            'budget_remaining' => $budget === null || ! $recommended ? null : round($budget - $recommended['total_cost'], 2),
            'within_budget_count' => collect($results)->where('within_budget', true)->count(),
        ];

        $id = DB::table('supplier_comparison_runs')->insertGetId([
            'company_id' => $companyId,
            'user_id' => auth()->id(),
            'query' => $query,
            'quantity' => $quantity,
            'unit' => $unit,
            'budget' => $budget,
            'status' => 'analyzed',
            'results' => json_encode($results),
            'summary' => json_encode($summary),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        AuditLogger::log('supplier_catalog_compared', 'supplier_comparison_run', $id, null, ['query' => $query, 'result_count' => count($results), 'potential_savings' => $summary['potential_savings']], $companyId);

        return ['id' => $id, 'results' => $results, 'summary' => $summary];
    }
}
