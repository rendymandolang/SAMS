@extends('layouts.app', ['title' => 'Goods Receipt · SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Inventory</p>
                    <h1>Goods Receipt</h1>
                </div>
            </header>

            @if (session('status'))
                <div class="notice">{{ session('status') }}</div>
            @endif

            <section class="card">
                <div class="toolbar">
                    <div>
                        <h2 style="margin-bottom:6px;">Daftar Goods Receipt</h2>
                        <p class="muted" style="margin:0;">Penerimaan barang dari Purchase Order approved.</p>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>No GR</th>
                            <th>Tanggal</th>
                            <th>PO</th>
                            <th>Gudang</th>
                            <th>Penerima</th>
                            <th>Status</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($goodsReceipts as $goodsReceipt)
                            <tr>
                                <td><strong>{{ $goodsReceipt->document_number }}</strong></td>
                                <td>{{ \Illuminate\Support\Carbon::parse($goodsReceipt->received_at)->format('d M Y H:i') }}</td>
                                <td>{{ $goodsReceipt->purchase_order_number }}</td>
                                <td>{{ $goodsReceipt->storage_location_name }}</td>
                                <td>{{ $goodsReceipt->receiver_name }}</td>
                                <td><span class="status">{{ $goodsReceipt->status }}</span></td>
                                <td>
                                    <div class="actions">
                                        <a class="link-action" href="{{ route('goods-receipts.show', $goodsReceipt->id) }}">Detail</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">Belum ada Goods Receipt. Buat GR dari PO yang sudah approved.</div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($goodsReceipts->hasPages())
                    <div class="pagination">{{ $goodsReceipts->links() }}</div>
                @endif
            </section>
        </main>
    </div>
@endsection
