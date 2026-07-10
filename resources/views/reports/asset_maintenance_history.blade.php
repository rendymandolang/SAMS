@extends('layouts.app', ['title' => 'Asset Maintenance History - SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Asset Intelligence</p>
                    <h1>Maintenance History Report</h1>
                </div>

                <a class="button secondary inline" href="{{ route('reports.assets.maintenance-history.print', request()->query()) }}" target="_blank">Print Report</a>
            </header>

            <section class="card" style="margin-bottom:18px;">
                <form method="GET" action="{{ route('reports.assets.maintenance-history') }}">
                    <div class="form-grid">
                        <label class="field">
                            <span class="label">Dari Request Date</span>
                            <input class="input" name="date_from" type="date" value="{{ $filters['date_from'] }}">
                        </label>
                        <label class="field">
                            <span class="label">Sampai Request Date</span>
                            <input class="input" name="date_to" type="date" value="{{ $filters['date_to'] }}">
                        </label>
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
                            <span class="label">Asset</span>
                            <select class="input" name="asset_id">
                                <option value="">Semua asset</option>
                                @foreach ($assets as $asset)
                                    <option value="{{ $asset->id }}" @selected((int) $filters['asset_id'] === (int) $asset->id)>{{ $asset->asset_number }} - {{ $asset->asset_name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:10px;">
                        <a class="button secondary inline" href="{{ route('reports.assets.maintenance-history') }}">Reset</a>
                        <button class="button inline" type="submit">Tampilkan</button>
                    </div>
                </form>
            </section>

            <section class="grid stats" style="margin-bottom:18px;">
                <div class="card">
                    <div class="muted">Work Orders</div>
                    <div class="stat-value">{{ number_format($summary['work_order_count']) }}</div>
                    <div class="muted">Total WO dalam filter</div>
                </div>
                <div class="card">
                    <div class="muted">Open / Active</div>
                    <div class="stat-value">{{ number_format($summary['open_count']) }}</div>
                    <div class="muted">Masih perlu follow-up</div>
                </div>
                <div class="card">
                    <div class="muted">Overdue</div>
                    <div class="stat-value">{{ number_format($summary['overdue_count']) }}</div>
                    <div class="muted">Lewat scheduled date</div>
                </div>
                <div class="card">
                    <div class="muted">Actual Cost</div>
                    <div class="stat-value">Rp {{ number_format((float) $summary['actual_cost'], 0, ',', '.') }}</div>
                    <div class="muted">Avg {{ number_format((float) $summary['avg_days_open'], 1, ',', '.') }} hari open</div>
                </div>
            </section>

            <section class="grid content-grid">
                <section class="card">
                    <div class="toolbar">
                        <div>
                            <h2 style="margin-bottom:6px;">Maintenance History</h2>
                            <p class="muted" style="margin:0;">Histori WO dengan status kontrol, overdue, dan variance biaya.</p>
                        </div>
                        <span class="badge">{{ number_format($summary['completed_count']) }} completed</span>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>WO</th>
                                <th>Asset</th>
                                <th>Type</th>
                                <th>Priority</th>
                                <th>Dates</th>
                                <th>Vendor</th>
                                <th>Cost</th>
                                <th>Days</th>
                                <th>Control</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td><a class="link-action" href="{{ route('asset-maintenances.show', $row->id) }}">{{ $row->document_number }}</a><br><span class="muted">{{ $row->requester_name }}</span></td>
                                    <td><strong>{{ $row->asset_number }}</strong><br><span class="muted">{{ $row->asset_name }}</span></td>
                                    <td><span class="status">{{ $row->maintenance_type }}</span></td>
                                    <td><span class="status">{{ $row->priority }}</span></td>
                                    <td>
                                        Req {{ \Illuminate\Support\Carbon::parse($row->request_date)->format('d M Y') }}<br>
                                        <span class="muted">Sch {{ $row->scheduled_date ? \Illuminate\Support\Carbon::parse($row->scheduled_date)->format('d M Y') : '-' }}</span>
                                    </td>
                                    <td>{{ $row->vendor_name ?: '-' }}</td>
                                    <td>
                                        Est Rp {{ number_format((float) $row->estimated_cost, 0, ',', '.') }}<br>
                                        <span class="muted">Act Rp {{ number_format((float) $row->actual_cost, 0, ',', '.') }}</span>
                                    </td>
                                    <td>{{ number_format((float) $row->days_open, 0, ',', '.') }}</td>
                                    <td><span class="status">{{ $row->control_status }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9"><div class="empty-state">Tidak ada maintenance history pada filter ini.</div></td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <aside class="card">
                    <p class="eyebrow">Asset ranking</p>
                    <h2>High Attention Assets</h2>
                    <p class="muted" style="line-height:1.7;">Asset dengan biaya atau frekuensi maintenance tertinggi perlu diprioritaskan untuk keputusan repair, replace, atau preventive schedule.</p>

                    <div class="module-list">
                        @forelse ($assetRankings as $asset)
                            <div class="module-row">
                                <div>
                                    <strong>{{ $asset->asset_number }}</strong>
                                    <p class="muted" style="margin:6px 0 0;line-height:1.55;">{{ $asset->asset_name }}<br>{{ $asset->work_order_count }} WO - Rp {{ number_format((float) $asset->actual_cost, 0, ',', '.') }}</p>
                                </div>
                                <span class="badge {{ $asset->overdue_count > 0 ? 'next' : '' }}">{{ $asset->overdue_count }} overdue</span>
                            </div>
                        @empty
                            <div class="empty-state">Belum ada ranking asset.</div>
                        @endforelse
                    </div>
                </aside>
            </section>
        </main>
    </div>
@endsection
