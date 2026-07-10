@extends('layouts.app', ['title' => 'Stock On Hand · SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Inventory</p>
                    <h1>Stock On Hand</h1>
                </div>
            </header>

            <section class="grid stats" style="margin-bottom:18px;">
                <div class="card">
                    <div class="muted">Item x Lokasi</div>
                    <div class="stat-value">{{ number_format((int) ($summary->item_location_count ?? 0)) }}</div>
                    <div class="muted">Saldo aktif</div>
                </div>
                <div class="card">
                    <div class="muted">Nilai Stok</div>
                    <div class="stat-value">Rp {{ number_format((float) ($summary->total_stock_value ?? 0), 0, ',', '.') }}</div>
                    <div class="muted">Berdasarkan stock movement</div>
                </div>
            </section>

            <section class="card">
                <div class="toolbar">
                    <div>
                        <h2 style="margin-bottom:6px;">Saldo stok per gudang</h2>
                        <p class="muted" style="margin:0;">Dihitung dari ledger stock movement yang sudah diposting.</p>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Lokasi</th>
                            <th>SKU</th>
                            <th>Item</th>
                            <th>Qty On Hand</th>
                            <th>Satuan</th>
                            <th>Average Cost</th>
                            <th>Stock Value</th>
                            <th>Last Movement</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td><strong>{{ $row->location_code }}</strong><br><span class="muted">{{ $row->location_name }}</span></td>
                                <td>{{ $row->sku }}</td>
                                <td><strong>{{ $row->item_name }}</strong></td>
                                <td>{{ number_format((float) $row->quantity_on_hand, 2, ',', '.') }}</td>
                                <td>{{ $row->unit_code }}</td>
                                <td>Rp {{ number_format((float) $row->average_cost, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format((float) $row->stock_value, 0, ',', '.') }}</td>
                                <td>{{ $row->last_movement_at ? \Illuminate\Support\Carbon::parse($row->last_movement_at)->format('d M Y H:i') : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">Belum ada stok. Posting Goods Receipt dulu untuk membuat stock movement.</div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($rows->hasPages())
                    <div class="pagination">{{ $rows->links() }}</div>
                @endif
            </section>
        </main>
    </div>
@endsection
