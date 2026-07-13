<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class AiQueryService
{
    public function ask(int $companyId, string $question): array
    {
        app(AiUsageManager::class)->ensureAllowed($companyId);
        $snapshot = app(AiInsightService::class)->snapshot($companyId);
        $normalized = mb_strtolower($question);
        [$intent, $answer] = match (true) {
            str_contains($normalized, 'budget') => ['budget', $this->budget($snapshot)],
            str_contains($normalized, 'stok') || str_contains($normalized, 'stock') || str_contains($normalized, 'reorder') => ['stock', $this->stock($snapshot)],
            str_contains($normalized, 'supplier') || str_contains($normalized, 'pemasok') => ['supplier', $this->supplier($snapshot)],
            str_contains($normalized, 'maintenance') || str_contains($normalized, 'perawatan') || str_contains($normalized, 'aset') => ['maintenance', $this->maintenance($snapshot)],
            str_contains($normalized, 'approval') || str_contains($normalized, 'persetujuan') || str_contains($normalized, 'pending') => ['approval', "Terdapat {$snapshot['pending_purchase_requests']} PR dan {$snapshot['pending_purchase_orders']} PO yang menunggu keputusan."],
            default => ['help', 'Saya dapat menjawab tentang budget, stok/reorder, supplier, maintenance/aset, dan approval. Pertanyaan tidak diterjemahkan menjadi SQL bebas.'],
        };

        $id = DB::table('ai_interactions')->insertGetId(['company_id' => $companyId, 'user_id' => auth()->id(), 'type' => 'query', 'intent' => $intent, 'question' => $question, 'answer' => $answer, 'provider' => 'local', 'created_at' => now(), 'updated_at' => now()]);
        AuditLogger::log('ai_query_answered', 'ai_interaction', $id, null, ['intent' => $intent], $companyId);

        return compact('id', 'intent', 'answer');
    }

    private function budget(array $s): string
    {
        $b = $s['budget'];
        $remaining = $b['allocated'] - $b['committed'] - $b['actual'];

        return 'Budget teralokasi Rp '.number_format($b['allocated'], 0, ',', '.').', committed Rp '.number_format($b['committed'], 0, ',', '.').', actual Rp '.number_format($b['actual'], 0, ',', '.').', dan tersisa Rp '.number_format($remaining, 0, ',', '.').'.';
    }

    private function stock(array $s): string
    {
        $top = $s['stock_forecasts'][0] ?? null;

        return $top ? "Prioritas reorder: {$top['sku']} sebanyak {$top['recommended_reorder']} unit; days cover ".($top['days_cover'] ?? 'belum cukup data').'.' : 'Belum ada item yang memiliki pola pemakaian atau kebutuhan reorder terdeteksi.';
    }

    private function supplier(array $s): string
    {
        $top = $s['supplier_risks'][0] ?? null;

        return $top ? "Supplier dengan risiko tertinggi: {$top['name']} dengan skor {$top['risk_score']}/100 ({$top['risk_level']})." : 'Belum ada histori transaksi supplier yang cukup untuk dinilai.';
    }

    private function maintenance(array $s): string
    {
        $top = $s['maintenance_predictions'][0] ?? null;

        return $top ? "Aset prioritas: {$top['asset_number']} dengan skor {$top['risk_score']}/100 dan prediksi maintenance {$top['predicted_maintenance_date']} (confidence {$top['confidence']})." : 'Belum ada aset aktif yang dapat diprediksi.';
    }
}
