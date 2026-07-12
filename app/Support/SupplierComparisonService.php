<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class SupplierComparisonService
{
    public function compare(int $companyId, string $query, float $quantity, string $unit, ?float $budget): array
    {
        $unit = strtoupper($unit); $tokens=array_values(array_filter(preg_split('/\s+/u',mb_strtolower(trim($query)))));
        $rows=DB::table('supplier_catalog_items')->join('supplier_catalogs','supplier_catalogs.id','=','supplier_catalog_items.supplier_catalog_id')->join('suppliers','suppliers.id','=','supplier_catalogs.supplier_id')
            ->where('supplier_catalogs.company_id',$companyId)->where('supplier_catalogs.status','published')->where('supplier_catalog_items.status','published')->where('supplier_catalog_items.normalized_unit',$unit)
            ->where(function($q)use($tokens){foreach($tokens as $token)$q->where(function($sub)use($token){$sub->whereRaw('LOWER(supplier_catalog_items.source_name) LIKE ?',['%'.$token.'%'])->orWhereRaw('LOWER(COALESCE(supplier_catalog_items.brand,\'\')) LIKE ?',['%'.$token.'%'])->orWhereRaw('LOWER(COALESCE(supplier_catalog_items.category,\'\')) LIKE ?',['%'.$token.'%']);});})
            ->where(function($q){$q->whereNull('supplier_catalogs.valid_from')->orWhereDate('supplier_catalogs.valid_from','<=',today());})->where(function($q){$q->whereNull('supplier_catalogs.valid_until')->orWhereDate('supplier_catalogs.valid_until','>=',today());})
            ->select('supplier_catalog_items.*','supplier_catalogs.currency','supplier_catalogs.name as catalog_name','suppliers.id as supplier_id','suppliers.name as supplier_name')->limit(100)->get();
        $risk=collect(app(AiPredictiveAnalytics::class)->supplierRisks($companyId))->keyBy('supplier_id');
        $results=$rows->map(function(object $row)use($quantity,$budget,$risk):array{$total=(float)$row->normalized_unit_price*$quantity;$supplierRisk=$risk->get((int)$row->supplier_id)['risk_score']??0;$score=round(100-min(40,$supplierRisk*.4)-min(20,(1-(float)$row->confidence)*20),1);return['catalog_item_id'=>(int)$row->id,'supplier_id'=>(int)$row->supplier_id,'supplier_name'=>$row->supplier_name,'product_name'=>$row->source_name,'brand'=>$row->brand,'catalog_name'=>$row->catalog_name,'currency'=>$row->currency,'unit_price'=>(float)$row->normalized_unit_price,'quantity'=>$quantity,'unit'=>$row->normalized_unit,'total_cost'=>round($total,2),'budget_variance'=>$budget===null?null:round($budget-$total,2),'within_budget'=>$budget===null?null:$total<=$budget,'supplier_risk'=>$supplierRisk,'confidence'=>(float)$row->confidence,'recommendation_score'=>$score];})->sortBy([['within_budget','desc'],['total_cost','asc'],['recommendation_score','desc']])->values()->all();
        $id=DB::table('supplier_comparison_runs')->insertGetId(['company_id'=>$companyId,'user_id'=>auth()->id(),'query'=>$query,'quantity'=>$quantity,'unit'=>$unit,'budget'=>$budget,'results'=>json_encode($results),'created_at'=>now(),'updated_at'=>now()]);
        AuditLogger::log('supplier_catalog_compared','supplier_comparison_run',$id,null,['query'=>$query,'result_count'=>count($results)],$companyId);
        return['id'=>$id,'results'=>$results];
    }
}
