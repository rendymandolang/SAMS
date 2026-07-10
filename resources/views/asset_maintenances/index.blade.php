@extends('layouts.app', ['title' => 'Asset Maintenance - SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Asset Control</p>
                    <h1>Asset Maintenance</h1>
                </div>
            </header>

            @if (session('status'))
                <div class="notice">{{ session('status') }}</div>
            @endif

            <section class="card" style="margin-bottom:18px;">
                <form method="GET" action="{{ route('asset-maintenances.index') }}">
                    <div class="form-grid">
                        <label class="field">
                            <span class="label">Status</span>
                            <select class="input" name="status">
                                <option value="">Semua status</option>
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="field">
                            <span class="label">Priority</span>
                            <select class="input" name="priority">
                                <option value="">Semua priority</option>
                                @foreach ($priorities as $value => $label)
                                    <option value="{{ $value }}" @selected($filters['priority'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:10px;">
                        <a class="button secondary inline" href="{{ route('asset-maintenances.index') }}">Reset</a>
                        <button class="button inline" type="submit">Tampilkan</button>
                    </div>
                </form>
            </section>

            <section class="grid stats" style="margin-bottom:18px;">
                <div class="card"><div class="muted">Open</div><div class="stat-value">{{ number_format($summary['open_count']) }}</div><div class="muted">Baru dibuat</div></div>
                <div class="card"><div class="muted">In Progress</div><div class="stat-value">{{ number_format($summary['in_progress_count']) }}</div><div class="muted">Dalam pengerjaan</div></div>
                <div class="card"><div class="muted">Completed</div><div class="stat-value">{{ number_format($summary['completed_count']) }}</div><div class="muted">Selesai</div></div>
                <div class="card"><div class="muted">Actual Cost</div><div class="stat-value">Rp {{ number_format((float) $summary['actual_cost'], 0, ',', '.') }}</div><div class="muted">Biaya aktual</div></div>
            </section>

            <section class="card">
                <div class="toolbar">
                    <div>
                        <h2 style="margin-bottom:6px;">Maintenance Work Orders</h2>
                        <p class="muted" style="margin:0;">Histori perawatan aset berdasarkan status dan prioritas.</p>
                    </div>
                    <span class="badge">{{ $maintenances->total() }} WO</span>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>WO</th>
                            <th>Asset</th>
                            <th>Requester</th>
                            <th>Type</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Request</th>
                            <th>Cost</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($maintenances as $maintenance)
                            <tr>
                                <td><strong>{{ $maintenance->document_number }}</strong></td>
                                <td><strong>{{ $maintenance->asset_number }}</strong><br><span class="muted">{{ $maintenance->asset_name }}</span></td>
                                <td>{{ $maintenance->requester_name }}</td>
                                <td><span class="status">{{ $maintenance->maintenance_type }}</span></td>
                                <td><span class="status">{{ $maintenance->priority }}</span></td>
                                <td><span class="status">{{ $maintenance->status }}</span></td>
                                <td>{{ \Illuminate\Support\Carbon::parse($maintenance->request_date)->format('d M Y') }}</td>
                                <td>Rp {{ number_format((float) ($maintenance->actual_cost ?: $maintenance->estimated_cost), 0, ',', '.') }}</td>
                                <td><div class="actions"><a class="link-action" href="{{ route('asset-maintenances.show', $maintenance->id) }}">Detail</a><a class="link-action" href="{{ route('asset-maintenances.print', $maintenance->id) }}" target="_blank">Print</a></div></td>
                            </tr>
                        @empty
                            <tr><td colspan="9"><div class="empty-state">Belum ada maintenance work order.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $maintenances->links() }}
            </section>
        </main>
    </div>
@endsection
