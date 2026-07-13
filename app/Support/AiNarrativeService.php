<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class AiNarrativeService
{
    public function generate(int $companyId): array
    {
        app(AiUsageManager::class)->ensureAllowed($companyId);
        $s = app(AiInsightService::class)->snapshot($companyId);
        $budget = $s['budget'];
        $used = $budget['committed'] + $budget['actual'];
        $percent = $budget['allocated'] > 0 ? round($used / $budget['allocated'] * 100, 1) : 0;
        $stock = $s['stock_forecasts'][0] ?? null;
        $supplier = $s['supplier_risks'][0] ?? null;
        $asset = $s['maintenance_predictions'][0] ?? null;
        $answer = "Ringkasan operasional: pemakaian dan komitmen budget mencapai {$percent}%. Approval tertunda terdiri dari {$s['pending_purchase_requests']} PR dan {$s['pending_purchase_orders']} PO. ";
        $answer .= $stock ? "Prioritas stok adalah {$stock['sku']} dengan rekomendasi reorder {$stock['recommended_reorder']} unit. " : 'Belum ada rekomendasi reorder berbasis histori. ';
        $answer .= $supplier ? "Supplier berisiko tertinggi adalah {$supplier['name']} ({$supplier['risk_score']}/100). " : 'Histori supplier belum cukup untuk ranking risiko. ';
        $answer .= $asset ? "Aset prioritas maintenance adalah {$asset['asset_number']} dengan prediksi {$asset['predicted_maintenance_date']} dan confidence {$asset['confidence']}." : 'Belum ada aset aktif untuk prediksi maintenance.';

        $id = DB::table('ai_interactions')->insertGetId(['company_id' => $companyId, 'user_id' => auth()->id(), 'type' => 'narrative', 'intent' => 'executive_summary', 'answer' => $answer, 'provider' => 'local', 'created_at' => now(), 'updated_at' => now()]);
        AuditLogger::log('ai_narrative_generated', 'ai_interaction', $id, null, ['intent' => 'executive_summary'], $companyId);

        return compact('id', 'answer');
    }
}
