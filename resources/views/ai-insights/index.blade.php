@extends('layouts.app')

@section('title', __('navigation.items.ai_insights'))

@section('body')
<div class="app-shell">
    @include('partials.sidebar')
    <main class="main">
    <div class="page-header">
        <div><p class="eyebrow">SuperSoft Intelligence · {{ $company->name }}</p><h1>AI Insight Center</h1><p>Analisis read-only berbasis data perusahaan aktif. Insight tidak dapat mengubah transaksi.</p></div>
        @if (auth()->user()->hasPermission('intelligence.generate'))
            <form method="POST" action="{{ route('ai-insights.generate') }}">@csrf<button class="button primary" type="submit">Generate Insight</button></form>
        @endif
    </div>

    @if (session('status')) <div class="notice">{{ session('status') }}</div> @endif
    @if ($errors->has('ai')) <div class="notice">{{ $errors->first('ai') }}</div> @endif

    <section class="card" style="margin-bottom:18px;">
        <h2>Operational Snapshot</h2>
        <div class="detail-grid">
            <div class="detail-box"><div class="muted">PR Pending</div><div class="value">{{ $snapshot['pending_purchase_requests'] }}</div></div>
            <div class="detail-box"><div class="muted">PO Pending</div><div class="value">{{ $snapshot['pending_purchase_orders'] }}</div></div>
            <div class="detail-box"><div class="muted">Budget Actual</div><div class="value">Rp {{ number_format($snapshot['budget']['actual'], 0, ',', '.') }}</div></div>
            <div class="detail-box"><div class="muted">Negative Stock</div><div class="value">{{ $snapshot['negative_stock_items'] }}</div></div>
            <div class="detail-box"><div class="muted">Overdue Maintenance</div><div class="value">{{ $snapshot['overdue_maintenances'] }}</div></div>
        </div>
    </section>

    <div class="grid two-columns" style="margin-bottom:18px;">
        <section class="card">
            <h2>Stock Forecast · 90 Hari</h2>
            <div class="table-wrap"><table><thead><tr><th>Item</th><th>Stok</th><th>Days Cover</th><th>Reorder</th></tr></thead><tbody>
            @forelse ($snapshot['stock_forecasts'] as $row)
                <tr><td><strong>{{ $row['sku'] }}</strong><br><small>{{ $row['name'] }}</small></td><td>{{ number_format($row['current_stock'], 2, ',', '.') }}</td><td>{{ $row['days_cover'] ?? 'Data belum cukup' }}</td><td>{{ number_format($row['recommended_reorder'], 2, ',', '.') }}</td></tr>
            @empty <tr><td colspan="4">Belum ada pola pemakaian atau kebutuhan reorder.</td></tr> @endforelse
            </tbody></table></div>
        </section>
        <section class="card">
            <h2>Price Anomaly</h2>
            <div class="table-wrap"><table><thead><tr><th>Item</th><th>Harga Terbaru</th><th>Rerata</th><th>Deviasi</th></tr></thead><tbody>
            @forelse ($snapshot['price_anomalies'] as $row)
                <tr><td>{{ $row['sku'] }}</td><td>Rp {{ number_format($row['latest_price'], 0, ',', '.') }}</td><td>Rp {{ number_format($row['historical_average'], 0, ',', '.') }}</td><td><span class="status">{{ $row['deviation_percent'] }}%</span></td></tr>
            @empty <tr><td colspan="4">Belum ada anomali atau histori harga belum cukup.</td></tr> @endforelse
            </tbody></table></div>
        </section>
        <section class="card">
            <h2>Supplier Risk</h2>
            <div class="table-wrap"><table><thead><tr><th>Supplier</th><th>PO</th><th>Late</th><th>Reject</th><th>Risk</th></tr></thead><tbody>
            @forelse ($snapshot['supplier_risks'] as $row)
                <tr><td>{{ $row['name'] }}</td><td>{{ $row['order_count'] }}</td><td>{{ $row['late_orders'] }}</td><td>{{ $row['reject_rate_percent'] }}%</td><td><span class="status">{{ $row['risk_score'] }}/100</span></td></tr>
            @empty <tr><td colspan="5">Belum ada histori supplier yang dapat dinilai.</td></tr> @endforelse
            </tbody></table></div>
        </section>
        <section class="card">
            <h2>Maintenance Prediction</h2>
            <div class="table-wrap"><table><thead><tr><th>Aset</th><th>Kondisi</th><th>Prediksi</th><th>Risk</th><th>Confidence</th></tr></thead><tbody>
            @forelse ($snapshot['maintenance_predictions'] as $row)
                <tr><td><strong>{{ $row['asset_number'] }}</strong><br><small>{{ $row['asset_name'] }}</small></td><td>{{ $row['condition'] }}</td><td>{{ $row['predicted_maintenance_date'] }}</td><td><span class="status">{{ $row['risk_score'] }}/100</span></td><td>{{ $row['confidence'] }}</td></tr>
            @empty <tr><td colspan="5">Belum ada aset aktif untuk diprediksi.</td></tr> @endforelse
            </tbody></table></div>
        </section>
    </div>

    @forelse ($runs as $run)
        <section class="card" style="margin-bottom:18px;">
            <p class="eyebrow">{{ strtoupper($run->provider) }}{{ $run->model ? ' · '.$run->model : '' }} · {{ $run->created_at }}</p>
            <div class="grid two-columns">
                @foreach ($run->output as $insight)
                    <article class="detail-box">
                        <span class="status">{{ $insight['severity'] ?? 'info' }}</span>
                        <h3>{{ $insight['title'] ?? 'Insight' }}</h3>
                        <p>{{ $insight['evidence'] ?? '' }}</p>
                        <strong>Rekomendasi</strong><p>{{ $insight['recommendation'] ?? '' }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        <section class="card"><p>Belum ada insight. Gunakan mode lokal tanpa API key atau aktifkan provider OpenAI melalui konfigurasi server.</p></section>
    @endforelse
    </main>
</div>
@endsection
