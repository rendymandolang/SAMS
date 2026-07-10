@extends('layouts.app', ['title' => 'Purchasing Cycle Report - SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Purchasing Report</p>
                    <h1>Purchasing Cycle Report</h1>
                </div>

                <a class="button secondary inline" href="{{ route('reports.purchasing.cycle.print', request()->query()) }}" target="_blank">Print Report</a>
            </header>

            <section class="card" style="margin-bottom:18px;">
                <form method="GET" action="{{ route('reports.purchasing.cycle') }}">
                    <div class="form-grid">
                        <label class="field">
                            <span class="label">Dari Tanggal PR</span>
                            <input class="input" name="date_from" type="date" value="{{ $filters['date_from'] }}">
                        </label>

                        <label class="field">
                            <span class="label">Sampai Tanggal PR</span>
                            <input class="input" name="date_to" type="date" value="{{ $filters['date_to'] }}">
                        </label>

                        <label class="field">
                            <span class="label">Departemen</span>
                            <select class="input" name="department_id">
                                <option value="">Semua departemen</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected((int) $filters['department_id'] === (int) $department->id)>{{ $department->code }} - {{ $department->name }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="field">
                            <span class="label">Cycle Status</span>
                            <select class="input" name="cycle_status">
                                <option value="">Semua status</option>
                                @foreach ($cycleStatuses as $status => $label)
                                    <option value="{{ $status }}" @selected($filters['cycle_status'] === $status)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:10px;">
                        <a class="button secondary inline" href="{{ route('reports.purchasing.cycle') }}">Reset</a>
                        <button class="button inline" type="submit">Tampilkan</button>
                    </div>
                </form>
            </section>

            <section class="grid stats" style="margin-bottom:18px;">
                <div class="card">
                    <div class="muted">Documents</div>
                    <div class="stat-value">{{ number_format($summary['document_count']) }}</div>
                    <div class="muted">PR dalam filter</div>
                </div>
                <div class="card">
                    <div class="muted">PR Value</div>
                    <div class="stat-value">Rp {{ number_format((float) $summary['purchase_request_total'], 0, ',', '.') }}</div>
                    <div class="muted">Nilai estimasi request</div>
                </div>
                <div class="card">
                    <div class="muted">PO Value</div>
                    <div class="stat-value">Rp {{ number_format((float) $summary['purchase_order_total'], 0, ',', '.') }}</div>
                    <div class="muted">Nilai order supplier</div>
                </div>
                <div class="card">
                    <div class="muted">Control Watch</div>
                    <div class="stat-value">{{ number_format($summary['risk_count']) }}</div>
                    <div class="muted">Masih approval / receipt</div>
                </div>
            </section>

            <section class="card">
                <div class="toolbar">
                    <div>
                        <h2 style="margin-bottom:6px;">PR to PO to GR Tracking</h2>
                        <p class="muted" style="margin:0;">Melihat progress siklus pembelian dari request sampai penerimaan barang.</p>
                    </div>
                    <span class="badge">{{ number_format($summary['received_count']) }} completed</span>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>PR</th>
                            <th>Department</th>
                            <th>Requester</th>
                            <th>PO / Supplier</th>
                            <th>GR Latest</th>
                            <th>PR Value</th>
                            <th>PO Value</th>
                            <th>Variance</th>
                            <th>Receipt</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($rows as $row)
                            @php
                                $barColor = match ($row->cycle_status) {
                                    'completed' => '#20c997',
                                    'partial_received' => '#f59e0b',
                                    'awaiting_receipt', 'waiting_po_approval', 'waiting_pr_approval' => '#f97316',
                                    default => '#6259ca',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <a class="link-action" href="{{ route('purchase-requests.show', $row->id) }}">{{ $row->document_number }}</a>
                                    <div class="muted">{{ \Illuminate\Support\Carbon::parse($row->request_date)->format('d M Y') }} - {{ ucfirst($row->priority) }}</div>
                                </td>
                                <td><strong>{{ $row->department_code }}</strong><br><span class="muted">{{ $row->department_name }}</span></td>
                                <td>{{ $row->requester_name }}</td>
                                <td>
                                    @if ($row->purchase_order_id)
                                        <a class="link-action" href="{{ route('purchase-orders.show', $row->purchase_order_id) }}">{{ $row->purchase_order_number }}</a>
                                        <div class="muted">{{ $row->supplier_name }}</div>
                                    @else
                                        <span class="muted">Belum ada PO</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($row->goods_receipt_count > 0)
                                        <strong>{{ $row->latest_goods_receipt_number }}</strong>
                                        <div class="muted">{{ $row->goods_receipt_count }} GR - {{ \Illuminate\Support\Carbon::parse($row->latest_received_at)->format('d M Y') }}</div>
                                    @else
                                        <span class="muted">Belum ada GR</span>
                                    @endif
                                </td>
                                <td>Rp {{ number_format((float) $row->estimated_total, 0, ',', '.') }}</td>
                                <td>{{ $row->purchase_order_id ? 'Rp '.number_format((float) $row->purchase_order_total, 0, ',', '.') : '-' }}</td>
                                <td>
                                    @if ($row->variance_amount !== null)
                                        Rp {{ number_format((float) $row->variance_amount, 0, ',', '.') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td style="min-width:160px;">
                                    <div style="height:10px;border-radius:999px;background:#eef2ff;overflow:hidden;">
                                        <div style="height:10px;width:{{ (float) $row->received_percent }}%;background:{{ $barColor }};"></div>
                                    </div>
                                    <div class="muted" style="margin-top:6px;">{{ number_format((float) $row->received_percent, 1, ',', '.') }}% - {{ number_format((float) $row->received_quantity, 2, ',', '.') }}/{{ number_format((float) $row->ordered_quantity, 2, ',', '.') }}</div>
                                </td>
                                <td><span class="status">{{ str_replace('_', ' ', $row->cycle_status) }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10"><div class="empty-state">Tidak ada data purchasing cycle pada filter ini.</div></td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
@endsection
