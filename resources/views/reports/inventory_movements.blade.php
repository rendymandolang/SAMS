@extends('layouts.app', ['title' => 'Laporan Mutasi Stok · SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Inventory Report</p>
                    <h1>Laporan Mutasi Stok</h1>
                </div>

                <button class="button secondary inline" type="button" onclick="window.print()">Print</button>
            </header>

            <section class="card" style="margin-bottom:18px;">
                <form method="GET" action="{{ route('reports.inventory.movements') }}">
                    <div class="form-grid">
                        <label class="field">
                            <span class="label">Dari Tanggal</span>
                            <input class="input" name="date_from" type="date" value="{{ $filters['date_from'] }}">
                        </label>

                        <label class="field">
                            <span class="label">Sampai Tanggal</span>
                            <input class="input" name="date_to" type="date" value="{{ $filters['date_to'] }}">
                        </label>

                        <label class="field">
                            <span class="label">Gudang / Lokasi</span>
                            <select class="input" name="storage_location_id">
                                <option value="">Semua gudang</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}" @selected((int) $filters['storage_location_id'] === (int) $location->id)>{{ $location->code }} · {{ $location->name }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="field">
                            <span class="label">Item</span>
                            <select class="input" name="item_id">
                                <option value="">Semua item</option>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}" @selected((int) $filters['item_id'] === (int) $item->id)>{{ $item->sku }} · {{ $item->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:10px;">
                        <a class="button secondary inline" href="{{ route('reports.inventory.movements') }}">Reset</a>
                        <button class="button inline" type="submit">Tampilkan</button>
                    </div>
                </form>
            </section>

            <section class="grid stats" style="margin-bottom:18px;">
                <div class="card">
                    <div class="muted">Opening Qty</div>
                    <div class="stat-value">{{ number_format((float) $summary['opening_quantity'], 2, ',', '.') }}</div>
                    <div class="muted">Saldo awal periode</div>
                </div>
                <div class="card">
                    <div class="muted">Qty Masuk</div>
                    <div class="stat-value">{{ number_format((float) $summary['quantity_in'], 2, ',', '.') }}</div>
                    <div class="muted">Total movement plus</div>
                </div>
                <div class="card">
                    <div class="muted">Qty Keluar</div>
                    <div class="stat-value">{{ number_format((float) $summary['quantity_out'], 2, ',', '.') }}</div>
                    <div class="muted">Total movement minus</div>
                </div>
                <div class="card">
                    <div class="muted">Closing Qty</div>
                    <div class="stat-value">{{ number_format((float) $summary['closing_quantity'], 2, ',', '.') }}</div>
                    <div class="muted">Saldo akhir periode</div>
                </div>
            </section>

            <section class="card">
                <div class="toolbar">
                    <div>
                        <h2 style="margin-bottom:6px;">Detail Mutasi</h2>
                        <p class="muted" style="margin:0;">Saldo berjalan dihitung dari opening + seluruh movement dalam filter.</p>
                    </div>
                    <span class="badge">{{ number_format($summary['movement_count']) }} movement</span>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Dokumen</th>
                            <th>Jenis</th>
                            <th>Gudang</th>
                            <th>Item</th>
                            <th>Masuk</th>
                            <th>Keluar</th>
                            <th>Saldo Qty</th>
                            <th>Unit Cost</th>
                            <th>Nilai Movement</th>
                            <th>Saldo Nilai</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($row->movement_at)->format('d M Y H:i') }}</td>
                                <td><strong>{{ $row->notes ?: '-' }}</strong><br><span class="muted">{{ $row->source_type }} #{{ $row->source_id }}</span></td>
                                <td><span class="status">{{ str_replace('_', ' ', $row->movement_type) }}</span></td>
                                <td><strong>{{ $row->location_code }}</strong><br><span class="muted">{{ $row->location_name }}</span></td>
                                <td><strong>{{ $row->sku }}</strong><br><span class="muted">{{ $row->item_name }} ({{ $row->unit_code }})</span></td>
                                <td>{{ $row->quantity_in > 0 ? number_format((float) $row->quantity_in, 2, ',', '.') : '-' }}</td>
                                <td>{{ $row->quantity_out > 0 ? number_format((float) $row->quantity_out, 2, ',', '.') : '-' }}</td>
                                <td>{{ number_format((float) $row->running_quantity, 2, ',', '.') }}</td>
                                <td>Rp {{ number_format((float) $row->unit_cost, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format((float) $row->total_cost, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format((float) $row->running_value, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11">
                                    <div class="empty-state">Tidak ada mutasi stok pada filter ini.</div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
@endsection
