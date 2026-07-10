@extends('layouts.app', ['title' => $header->document_number.' · SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Stock Opname</p>
                    <h1>{{ $header->document_number }}</h1>
                </div>

                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <a class="button secondary inline" href="{{ route('stock-opnames.index') }}">Kembali</a>
                    <a class="button secondary inline" href="{{ route('stock-opnames.print', $header->id) }}" target="_blank">Print Opname</a>
                    @if ($header->status === 'draft' && auth()->user()->hasAnyRole(['super_admin', 'warehouse']))
                        <form method="POST" action="{{ route('stock-opnames.post', $header->id) }}">
                            @csrf
                            <button class="button inline" type="submit">Post Opname</button>
                        </form>
                    @endif
                </div>
            </header>

            @if (session('status'))
                <div class="notice">{{ session('status') }}</div>
            @endif

            <section class="card" style="margin-bottom:18px;">
                <div class="detail-grid">
                    <div class="detail-box">
                        <div class="muted">Status</div>
                        <div class="value"><span class="status">{{ $header->status }}</span></div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Gudang</div>
                        <div class="value">{{ $header->location_code }} · {{ $header->location_name }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Tanggal Hitung</div>
                        <div class="value">{{ \Illuminate\Support\Carbon::parse($header->count_date)->format('d M Y') }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Dibuat oleh</div>
                        <div class="value">{{ $header->creator_name }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Total Item</div>
                        <div class="value">{{ number_format($summary['line_count']) }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Nilai Selisih</div>
                        <div class="value">Rp {{ number_format((float) $summary['variance_value'], 0, ',', '.') }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Posted</div>
                        <div class="value">{{ $header->posted_at ? \Illuminate\Support\Carbon::parse($header->posted_at)->format('d M Y H:i') : '-' }}</div>
                    </div>
                </div>

                <div>
                    <div class="muted">Catatan</div>
                    <p style="margin:8px 0 0;line-height:1.7;">{{ $header->notes ?: '-' }}</p>
                </div>
            </section>

            <section class="card">
                <h2>Hasil Hitung Fisik</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Item</th>
                            <th>Qty Sistem</th>
                            <th>Qty Fisik</th>
                            <th>Selisih</th>
                            <th>Satuan</th>
                            <th>Unit Cost</th>
                            <th>Nilai Selisih</th>
                            <th>Catatan</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($items as $item)
                            @php
                                $varianceValue = (float) $item->variance_quantity * (float) $item->unit_cost;
                            @endphp
                            <tr>
                                <td>{{ $item->sku }}</td>
                                <td><strong>{{ $item->item_name }}</strong></td>
                                <td>{{ number_format((float) $item->system_quantity, 2, ',', '.') }}</td>
                                <td>{{ $item->counted_quantity === null ? '-' : number_format((float) $item->counted_quantity, 2, ',', '.') }}</td>
                                <td>{{ number_format((float) $item->variance_quantity, 2, ',', '.') }}</td>
                                <td>{{ $item->unit_code }}</td>
                                <td>Rp {{ number_format((float) $item->unit_cost, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format($varianceValue, 0, ',', '.') }}</td>
                                <td>{{ $item->notes ?: '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
@endsection
