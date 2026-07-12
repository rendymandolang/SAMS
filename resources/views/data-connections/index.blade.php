@extends('layouts.app')

@section('title', 'Data Connections')

@section('body')
<div class="app-shell">
    @include('partials.sidebar')
    <main class="main">
        <header class="page-header">
            <div><p class="eyebrow">Integration Control · {{ $company->name }}</p><h1>Data Connections</h1><p>Kelola sumber data eksternal secara aman tanpa menyimpan API key di database.</p></div>
            <span class="status">{{ $connections->where('is_active', true)->count() }} aktif</span>
        </header>

        @if (session('status')) <div class="notice">{{ session('status') }}</div> @endif
        @if (session('connection_warning')) <div class="error">{{ session('connection_warning') }}</div> @endif

        <section class="card" style="margin-bottom:18px;background:linear-gradient(135deg,var(--primary-soft),#fff 62%,var(--accent-soft));">
            <div class="detail-grid">
                <div class="detail-box"><div class="muted">Total koneksi</div><div class="value">{{ $connections->count() }}</div></div>
                <div class="detail-box"><div class="muted">Terhubung</div><div class="value">{{ $connections->where('status', 'connected')->count() }}</div></div>
                <div class="detail-box"><div class="muted">Butuh konfigurasi</div><div class="value">{{ $connections->where('status', 'needs_configuration')->count() }}</div></div>
                <div class="detail-box"><div class="muted">Penyimpanan rahasia</div><div class="value" style="font-size:16px;">Server `.env`</div></div>
            </div>
        </section>

        <div class="grid two-columns">
            @foreach ($connections as $connection)
                @php $settings = json_decode($connection->settings ?? '{}', true) ?: []; @endphp
                <article class="card">
                    <div style="display:flex;justify-content:space-between;gap:14px;align-items:flex-start;">
                        <div><p class="eyebrow">{{ $connection->category }}</p><h2 style="margin:0 0 6px;">{{ $connection->name }}</h2></div>
                        <span class="status">{{ str($connection->status)->replace('_', ' ')->title() }}</span>
                    </div>
                    <p class="muted" style="min-height:42px;">@if($connection->provider_key === 'api_co_id_bank_rate') Kurs live BRI, Mandiri, atau BCA untuk referensi pembelian. @elseif($connection->provider_key === 'bri_valas_official') Integrasi resmi BRI untuk sandbox dan production partner. @elseif($connection->provider_key === 'supplier_catalog_feed') Upload katalog supplier CSV, Excel, dan PDF yang sudah aktif. @else Sinkronisasi price list supplier melalui spreadsheet terjadwal. @endif</p>
                    <div class="detail-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));margin:14px 0;">
                        <div class="detail-box"><div class="muted">Jadwal</div><strong>{{ $connection->sync_interval_minutes ? ($connection->sync_interval_minutes >= 1440 ? 'Harian' : $connection->sync_interval_minutes.' menit') : 'Manual' }}</strong></div>
                        <div class="detail-box"><div class="muted">Respons terakhir</div><strong>{{ $connection->last_response_ms ? $connection->last_response_ms.' ms' : 'Belum diuji' }}</strong></div>
                    </div>
                    @if ($connection->last_message)<p class="muted" style="font-size:13px;">{{ $connection->last_message }}</p>@endif
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        @if (in_array($connection->provider_key, ['api_co_id_bank_rate', 'bri_valas_official'], true))
                            <form method="POST" action="{{ route('data-connections.test', $connection->id) }}">@csrf<button class="button secondary inline" type="submit">Uji Koneksi</button></form>
                        @endif
                        @if ($connection->status === 'connected')
                            <form method="POST" action="{{ route('data-connections.toggle', $connection->id) }}">@csrf @method('PUT')<button class="button {{ $connection->is_active ? 'secondary' : 'primary' }} inline" type="submit">{{ $connection->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                        @endif
                        @if ($connection->provider_key === 'supplier_catalog_feed')<a class="button secondary inline" href="{{ route('supplier-catalogs.index') }}">Buka Katalog</a>@endif
                    </div>
                    <p class="muted" style="margin:14px 0 0;font-size:11px;">Kredensial: {{ $connection->credential_source }} · Terakhir diuji: {{ $connection->last_tested_at ?: 'belum pernah' }}</p>
                </article>
            @endforeach
        </div>
    </main>
</div>
@endsection
