@extends('layouts.app', ['title' => $header->document_number.' · SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Purchase Order</p>
                    <h1>{{ $header->document_number }}</h1>
                </div>

                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <a class="button secondary inline" href="{{ route('purchase-orders.index') }}">Kembali</a>
                    <a class="button secondary inline" href="{{ route('purchase-orders.print', $header->id) }}" target="_blank">Print PO</a>
                    @if ($header->status === 'draft')
                        <form method="POST" action="{{ route('purchase-orders.submit', $header->id) }}">
                            @csrf
                            <button class="button inline" type="submit">Submit PO</button>
                        </form>
                    @endif
                    @if ($header->status === 'submitted')
                        <form method="POST" action="{{ route('purchase-orders.approve', $header->id) }}">
                            @csrf
                            <button class="button inline" type="submit">Approve PO</button>
                        </form>
                    @endif
                    @if ($header->status === 'approved' || $header->status === 'partial_received')
                        <a class="button inline" href="{{ route('goods-receipts.create-from-po', $header->id) }}">Buat GR</a>
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
                        <div class="muted">Supplier</div>
                        <div class="value">{{ $header->supplier_name }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Source PR</div>
                        <div class="value">{{ $header->purchase_request_number ?: '-' }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Total</div>
                        <div class="value">Rp {{ number_format((float) $header->total_amount, 0, ',', '.') }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Tanggal PO</div>
                        <div class="value">{{ \Illuminate\Support\Carbon::parse($header->order_date)->format('d M Y') }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Expected</div>
                        <div class="value">{{ $header->expected_date ? \Illuminate\Support\Carbon::parse($header->expected_date)->format('d M Y') : '-' }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Dibuat oleh</div>
                        <div class="value">{{ $header->creator_name }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Kontak Supplier</div>
                        <div class="value">{{ $header->contact_person ?: '-' }} {{ $header->supplier_phone ? '· '.$header->supplier_phone : '' }}</div>
                    </div>
                </div>

                <div>
                    <div class="muted">Catatan</div>
                    <p style="margin:8px 0 0;line-height:1.7;">{{ $header->notes ?: '-' }}</p>
                </div>
            </section>

            <section class="card">
                <h2>Item PO</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Received</th>
                            <th>Satuan</th>
                            <th>Harga</th>
                            <th>Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item->sku }}</td>
                                <td><strong>{{ $item->item_name }}</strong></td>
                                <td>{{ number_format((float) $item->quantity, 2, ',', '.') }}</td>
                                <td>{{ number_format((float) $item->received_quantity, 2, ',', '.') }}</td>
                                <td>{{ $item->unit_code }}</td>
                                <td>Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format((float) $item->line_total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
@endsection
