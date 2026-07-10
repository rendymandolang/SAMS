@extends('layouts.app', ['title' => $asset->asset_number.' - SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Asset Detail</p>
                    <h1>{{ $asset->asset_number }}</h1>
                </div>

                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <a class="button secondary inline" href="{{ route('assets.index') }}">Kembali</a>
                    <a class="button secondary inline" href="{{ route('assets.print', $asset->id) }}" target="_blank">Print Asset</a>
                </div>
            </header>

            @if (session('status'))
                <div class="notice">{{ session('status') }}</div>
            @endif

            <section class="card" style="margin-bottom:18px;">
                <div class="detail-grid">
                    <div class="detail-box">
                        <div class="muted">Status</div>
                        <div class="value"><span class="status">{{ $asset->status }}</span></div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Kondisi</div>
                        <div class="value"><span class="status">{{ $asset->condition }}</span></div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Nilai Perolehan</div>
                        <div class="value">Rp {{ number_format((float) $asset->acquisition_cost, 0, ',', '.') }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Tanggal Perolehan</div>
                        <div class="value">{{ \Illuminate\Support\Carbon::parse($asset->acquisition_date)->format('d M Y') }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Item</div>
                        <div class="value">{{ $asset->sku }} - {{ $asset->item_name }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Serial Number</div>
                        <div class="value">{{ $asset->serial_number ?: '-' }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Departemen</div>
                        <div class="value">{{ $asset->department_code ? $asset->department_code.' - '.$asset->department_name : '-' }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Lokasi</div>
                        <div class="value">{{ $asset->location_code ? $asset->location_code.' - '.$asset->location_name : '-' }}</div>
                    </div>
                </div>

                <div>
                    <div class="muted">Nama Asset</div>
                    <p style="margin:8px 0 18px;line-height:1.7;"><strong>{{ $asset->asset_name }}</strong></p>

                    <div class="muted">Catatan</div>
                    <p style="margin:8px 0 0;line-height:1.7;">{{ $asset->notes ?: '-' }}</p>
                </div>
            </section>

            <section class="card">
                <h2>Control Info</h2>
                <div class="detail-grid" style="margin-bottom:0;">
                    <div class="detail-box">
                        <div class="muted">Dibuat Oleh</div>
                        <div class="value">{{ $asset->creator_name }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Created At</div>
                        <div class="value">{{ \Illuminate\Support\Carbon::parse($asset->created_at)->format('d M Y H:i') }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Source GR Item</div>
                        <div class="value">{{ $asset->goods_receipt_number ?: ($asset->goods_receipt_item_id ?: '-') }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Asset ID</div>
                        <div class="value">#{{ $asset->id }}</div>
                    </div>
                </div>
            </section>
        </main>
    </div>
@endsection
