<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class AiInsightService
{
    public function generate(int $companyId): array
    {
        $driver = config('ai.driver', 'local');
        app(AiUsageManager::class)->ensureAllowed($companyId, $driver === 'openai');
        $snapshot = $this->snapshot($companyId);
        try {
            $result = $driver === 'openai' ? $this->openAi($snapshot) : $this->local($snapshot);
        } catch (Throwable $exception) {
            $runId = DB::table('ai_insight_runs')->insertGetId([
                'company_id' => $companyId, 'user_id' => auth()->id(), 'provider' => $driver,
                'model' => $driver === 'openai' ? config('ai.openai.model') : null,
                'status' => 'failed', 'input_snapshot' => json_encode($snapshot), 'output' => json_encode([]),
                'error_message' => mb_substr($exception->getMessage(), 0, 2000), 'created_at' => now(), 'updated_at' => now(),
            ]);
            AuditLogger::log('ai_insights_failed', 'ai_insight_run', $runId, null, ['provider' => $driver], $companyId);
            throw $exception;
        }

        $runId = DB::table('ai_insight_runs')->insertGetId([
            'company_id' => $companyId,
            'user_id' => auth()->id(),
            'provider' => $driver,
            'model' => $result['model'] ?? null,
            'status' => 'completed',
            'input_snapshot' => json_encode($snapshot),
            'output' => json_encode($result['insights']),
            'input_tokens' => $result['input_tokens'] ?? null,
            'output_tokens' => $result['output_tokens'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditLogger::log('ai_insights_generated', 'ai_insight_run', $runId, null, ['provider' => $driver], $companyId);

        return ['id' => $runId, 'snapshot' => $snapshot, ...$result];
    }

    public function snapshot(int $companyId): array
    {
        $predictive = app(AiPredictiveAnalytics::class);
        $budget = DB::table('budget_lines')->join('budgets', 'budgets.id', '=', 'budget_lines.budget_id')
            ->where('budgets.company_id', $companyId)
            ->selectRaw('COALESCE(SUM(allocated_amount),0) allocated, COALESCE(SUM(committed_amount),0) committed, COALESCE(SUM(actual_amount),0) actual')->first();

        return [
            'generated_at' => now()->toIso8601String(),
            'pending_purchase_requests' => DB::table('purchase_requests')->where('company_id', $companyId)->where('status', 'submitted')->count(),
            'pending_purchase_orders' => DB::table('purchase_orders')->where('company_id', $companyId)->where('status', 'submitted')->count(),
            'budget' => ['allocated' => (float) $budget->allocated, 'committed' => (float) $budget->committed, 'actual' => (float) $budget->actual],
            'active_assets' => DB::table('asset_registers')->where('company_id', $companyId)->where('status', 'active')->whereNull('deleted_at')->count(),
            'open_maintenances' => DB::table('asset_maintenances')->where('company_id', $companyId)->whereIn('status', ['open', 'in_progress'])->whereNull('deleted_at')->count(),
            'overdue_maintenances' => DB::table('asset_maintenances')->where('company_id', $companyId)->whereIn('status', ['open', 'in_progress'])->whereDate('scheduled_date', '<', today())->whereNull('deleted_at')->count(),
            'negative_stock_items' => DB::table('stock_movements')
                ->where('company_id', $companyId)
                ->select('storage_location_id', 'item_id')
                ->selectRaw('SUM(quantity) as stock_balance')
                ->groupBy('storage_location_id', 'item_id')
                ->havingRaw('SUM(quantity) < 0')
                ->get()
                ->count(),
            'stock_forecasts' => $predictive->stockForecasts($companyId),
            'price_anomalies' => $predictive->priceAnomalies($companyId),
            'supplier_risks' => $predictive->supplierRisks($companyId),
            'maintenance_predictions' => $predictive->maintenancePredictions($companyId),
        ];
    }

    private function local(array $snapshot): array
    {
        $insights = [];
        $used = $snapshot['budget']['committed'] + $snapshot['budget']['actual'];
        $ratio = $snapshot['budget']['allocated'] > 0 ? $used / $snapshot['budget']['allocated'] : 0;
        if ($ratio >= .9) $insights[] = $this->insight('critical', 'Budget hampir habis', 'Pemakaian dan komitmen budget mencapai '.round($ratio * 100, 1).'%.', 'Tinjau komitmen terbuka dan hentikan pembelian non-prioritas.');
        elseif ($ratio >= .75) $insights[] = $this->insight('warning', 'Budget perlu dipantau', 'Pemakaian dan komitmen budget mencapai '.round($ratio * 100, 1).'%.', 'Prioritaskan kebutuhan operasional utama.');
        if ($snapshot['pending_purchase_requests'] + $snapshot['pending_purchase_orders'] > 0) $insights[] = $this->insight('warning', 'Approval tertunda', $snapshot['pending_purchase_requests'].' PR dan '.$snapshot['pending_purchase_orders'].' PO menunggu keputusan.', 'Buka Approval Center dan selesaikan dokumen tertua.');
        if ($snapshot['negative_stock_items'] > 0) $insights[] = $this->insight('critical', 'Saldo stok negatif', $snapshot['negative_stock_items'].' kombinasi item-lokasi memiliki saldo negatif.', 'Audit movement dan lakukan stock opname terkontrol.');
        if ($snapshot['overdue_maintenances'] > 0) $insights[] = $this->insight('warning', 'Maintenance lewat jadwal', $snapshot['overdue_maintenances'].' pekerjaan maintenance sudah melewati jadwal.', 'Prioritaskan aset kritikal dan tetapkan penanggung jawab.');
        if ($forecast = collect($snapshot['stock_forecasts'])->first(fn (array $row) => $row['recommended_reorder'] > 0)) $insights[] = $this->insight('warning', 'Prediksi kebutuhan stok', $forecast['sku'].' diperkirakan membutuhkan reorder '.$forecast['recommended_reorder'].' unit'.($forecast['days_cover'] !== null ? ' dengan '.$forecast['days_cover'].' hari persediaan.' : '.'), 'Validasi pemakaian aktual dan buat PR jika kebutuhan terkonfirmasi.');
        if ($price = collect($snapshot['price_anomalies'])->first()) $insights[] = $this->insight($price['severity'], 'Anomali harga pembelian', $price['sku'].' menyimpang '.$price['deviation_percent'].'% dari rerata historis.', 'Bandingkan quotation dan minta klarifikasi supplier sebelum approval.');
        if ($supplier = collect($snapshot['supplier_risks'])->first(fn (array $row) => $row['risk_score'] >= 40)) $insights[] = $this->insight($supplier['risk_level'] === 'high' ? 'critical' : 'warning', 'Risiko supplier', $supplier['name'].' memiliki risk score '.$supplier['risk_score'].'/100.', 'Review ketepatan waktu, reject rate, dan alternatif supplier.');
        if ($asset = collect($snapshot['maintenance_predictions'])->first(fn (array $row) => $row['risk_score'] >= 40)) $insights[] = $this->insight($asset['risk_level'] === 'high' ? 'critical' : 'warning', 'Prediksi maintenance aset', $asset['asset_number'].' memiliki risk score '.$asset['risk_score'].'/100 dan estimasi maintenance '.$asset['predicted_maintenance_date'].'.', 'Jadwalkan inspeksi preventif sebelum tanggal prediksi.');
        if ($insights === []) $insights[] = $this->insight('healthy', 'Operasional terkendali', 'Tidak ada anomali prioritas tinggi pada snapshot saat ini.', 'Pertahankan monitoring dan review berkala.');

        return ['provider' => 'local', 'model' => null, 'insights' => $insights];
    }

    private function openAi(array $snapshot): array
    {
        $key = config('ai.openai.api_key');
        if (! $key) throw new RuntimeException('OPENAI_API_KEY belum dikonfigurasi.');
        $model = config('ai.openai.model');
        $response = Http::withToken($key)->timeout(config('ai.openai.timeout'))->post('https://api.openai.com/v1/responses', [
            'model' => $model,
            'instructions' => 'You are a read-only operations analyst. Return only a JSON array of objects with severity, title, evidence, and recommendation. Never recommend bypassing approvals or changing records automatically.',
            'input' => json_encode($snapshot),
        ])->throw()->json();
        $outputItem = collect($response['output'] ?? [])->flatMap(fn ($item) => $item['content'] ?? [])->firstWhere('type', 'output_text');
        $text = is_array($outputItem) ? ($outputItem['text'] ?? null) : null;
        $insights = $text ? json_decode($text, true) : null;
        if (! is_array($insights)) throw new RuntimeException('Respons AI tidak memiliki format insight yang valid.');

        return ['provider' => 'openai', 'model' => $model, 'insights' => $insights, 'input_tokens' => $response['usage']['input_tokens'] ?? null, 'output_tokens' => $response['usage']['output_tokens'] ?? null];
    }

    private function insight(string $severity, string $title, string $evidence, string $recommendation): array
    {
        return compact('severity', 'title', 'evidence', 'recommendation');
    }
}
