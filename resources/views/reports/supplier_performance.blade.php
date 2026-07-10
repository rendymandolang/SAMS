@extends('layouts.app', ['title' => 'Supplier Performance Report - SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Purchasing Report</p>
                    <h1>Supplier Performance</h1>
                </div>

                <a class="button secondary inline" href="{{ route('reports.purchasing.suppliers.print', request()->query()) }}" target="_blank">Print Report</a>
            </header>

            <section class="card" style="margin-bottom:18px;">
                <form method="GET" action="{{ route('reports.purchasing.suppliers') }}">
                    <div class="form-grid">
                        <label class="field">
                            <span class="label">Dari Tanggal PO</span>
                            <input class="input" name="date_from" type="date" value="{{ $filters['date_from'] }}">
                        </label>

                        <label class="field">
                            <span class="label">Sampai Tanggal PO</span>
                            <input class="input" name="date_to" type="date" value="{{ $filters['date_to'] }}">
                        </label>

                        <label class="field full">
                            <span class="label">Supplier</span>
                            <select class="input" name="supplier_id">
                                <option value="">Semua supplier</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" @selected((int) $filters['supplier_id'] === (int) $supplier->id)>{{ $supplier->code }} - {{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:10px;">
                        <a class="button secondary inline" href="{{ route('reports.purchasing.suppliers') }}">Reset</a>
                        <button class="button inline" type="submit">Tampilkan</button>
                    </div>
                </form>
            </section>

            <section class="grid stats" style="margin-bottom:18px;">
                <div class="card">
                    <div class="muted">Supplier</div>
                    <div class="stat-value">{{ number_format($summary['supplier_count']) }}</div>
                    <div class="muted">Supplier dengan PO</div>
                </div>
                <div class="card">
                    <div class="muted">Purchase Order</div>
                    <div class="stat-value">{{ number_format($summary['purchase_order_count']) }}</div>
                    <div class="muted">PO dalam periode</div>
                </div>
                <div class="card">
                    <div class="muted">Order Value</div>
                    <div class="stat-value">Rp {{ number_format((float) $summary['total_order_amount'], 0, ',', '.') }}</div>
                    <div class="muted">Total nilai PO</div>
                </div>
                <div class="card">
                    <div class="muted">Watch List</div>
                    <div class="stat-value">{{ number_format($summary['watch_count']) }}</div>
                    <div class="muted">Supplier perlu perhatian</div>
                </div>
            </section>

            <section class="card">
                <div class="toolbar">
                    <div>
                        <h2 style="margin-bottom:6px;">Supplier Scorecard</h2>
                        <p class="muted" style="margin:0;">Nilai supplier dilihat dari nilai order, completion rate, rejection rate, dan PO yang masih aktif.</p>
                    </div>
                    <span class="badge">Rp {{ number_format((float) $summary['accepted_value'], 0, ',', '.') }} accepted</span>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Supplier</th>
                            <th>PO</th>
                            <th>Completed</th>
                            <th>Active</th>
                            <th>Order Value</th>
                            <th>Accepted Value</th>
                            <th>Accepted Qty</th>
                            <th>Rejected Qty</th>
                            <th>Completion</th>
                            <th>Reject Rate</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($rows as $row)
                            @php
                                $barColor = match ($row->performance_status) {
                                    'excellent' => '#20c997',
                                    'watch' => '#f59e0b',
                                    'risk' => '#ef4444',
                                    default => '#6259ca',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $row->supplier_code }}</strong><br>
                                    <span class="muted">{{ $row->supplier_name }}</span>
                                </td>
                                <td>{{ number_format($row->purchase_order_count) }}</td>
                                <td>{{ number_format($row->completed_order_count) }}</td>
                                <td>{{ number_format($row->active_order_count) }}</td>
                                <td>Rp {{ number_format((float) $row->total_order_amount, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format((float) $row->accepted_value, 0, ',', '.') }}</td>
                                <td>{{ number_format((float) $row->accepted_quantity, 2, ',', '.') }}</td>
                                <td>{{ number_format((float) $row->rejected_quantity, 2, ',', '.') }}</td>
                                <td style="min-width:150px;">
                                    <div style="height:10px;border-radius:999px;background:#eef2ff;overflow:hidden;">
                                        <div style="height:10px;width:{{ (float) $row->completion_rate }}%;background:{{ $barColor }};"></div>
                                    </div>
                                    <div class="muted" style="margin-top:6px;">{{ number_format((float) $row->completion_rate, 1, ',', '.') }}%</div>
                                </td>
                                <td>{{ number_format((float) $row->rejection_rate, 1, ',', '.') }}%</td>
                                <td><span class="status">{{ $row->performance_status }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11"><div class="empty-state">Tidak ada performa supplier pada filter ini.</div></td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
@endsection
