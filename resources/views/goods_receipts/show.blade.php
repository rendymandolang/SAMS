@extends('layouts.app', ['title' => $header->document_number.' - SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Goods Receipt</p>
                    <h1>{{ $header->document_number }}</h1>
                </div>

                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <a class="button secondary inline" href="{{ route('goods-receipts.index') }}">Kembali</a>
                    <a class="button secondary inline" href="{{ route('goods-receipts.print', $header->id) }}" target="_blank">Print GR</a>
                    @if ($header->status === 'draft' && auth()->user()->hasAnyRole(['super_admin', 'warehouse']))
                        <form method="POST" action="{{ route('goods-receipts.post', $header->id) }}">
                            @csrf
                            <button class="button inline" type="submit">Post GR</button>
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
                        <div class="muted">PO</div>
                        <div class="value">{{ $header->purchase_order_number }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Gudang</div>
                        <div class="value">{{ $header->storage_location_name }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Penerima</div>
                        <div class="value">{{ $header->receiver_name }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Tanggal Terima</div>
                        <div class="value">{{ \Illuminate\Support\Carbon::parse($header->received_at)->format('d M Y H:i') }}</div>
                    </div>
                    <div class="detail-box">
                        <div class="muted">Surat Jalan</div>
                        <div class="value">{{ $header->supplier_delivery_number ?: '-' }}</div>
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
                <h2>Item GR</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Item</th>
                            <th>Accepted</th>
                            <th>Rejected</th>
                            <th>Satuan</th>
                            <th>Unit Cost</th>
                            <th>Lot</th>
                            <th>Expiry</th>
                            <th>Asset</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item->sku }}</td>
                                <td><strong>{{ $item->item_name }}</strong></td>
                                <td>{{ number_format((float) $item->accepted_quantity, 2, ',', '.') }}</td>
                                <td>{{ number_format((float) $item->rejected_quantity, 2, ',', '.') }}</td>
                                <td>{{ $item->unit_code }}</td>
                                <td>Rp {{ number_format((float) $item->unit_cost, 0, ',', '.') }}</td>
                                <td>{{ $item->lot_number ?: '-' }}</td>
                                <td>{{ $item->expiry_date ?: '-' }}</td>
                                <td>
                                    @if ($item->registered_asset_id)
                                        <a class="link-action" href="{{ route('assets.show', $item->registered_asset_id) }}">{{ $item->registered_asset_number }}</a>
                                    @elseif ($header->status === 'posted' && $item->item_type === 'asset' && (float) $item->accepted_quantity > 0 && auth()->user()->hasAnyRole(['super_admin', 'purchasing', 'warehouse']))
                                        <a class="link-action" href="{{ route('assets.create-from-gr-item', $item->id) }}">Register Asset</a>
                                    @else
                                        <span class="muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
@endsection
