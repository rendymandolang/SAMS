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
