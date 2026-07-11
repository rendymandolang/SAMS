@extends('layouts.app', ['title' => 'Budget Control - SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Finance Control</p>
                    <h1>Budget Control</h1>
                </div>

                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <a class="button secondary inline" href="{{ route('budget-control.export', request()->query()) }}">Export CSV</a>
                    <a class="button secondary inline" href="{{ route('budget-control.print', request()->query()) }}" target="_blank">Print Report</a>
                </div>
            </header>

            <section class="card" style="margin-bottom:18px;">
                <form method="GET" action="{{ route('budget-control.index') }}">
                    <div class="form-grid">
                        <label class="field">
                            <span class="label">Departemen</span>
                            <select class="input" name="department_id" onchange="this.form.submit()">
                                <option value="">Semua departemen</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected((int) $filters['department_id'] === (int) $department->id)>{{ $department->code }} - {{ $department->name }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="field">
                            <span class="label">Budget</span>
                            <select class="input" name="budget_id">
                                <option value="">Semua budget</option>
                                @foreach ($budgets as $budget)
                                    <option value="{{ $budget->id }}" @selected((int) $filters['budget_id'] === (int) $budget->id)>{{ $budget->department_code }} - {{ $budget->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:10px;">
                        <a class="button secondary inline" href="{{ route('budget-control.index') }}">Reset</a>
                        <button class="button inline" type="submit">Tampilkan</button>
                    </div>
                </form>
            </section>

            <section class="grid stats" style="margin-bottom:18px;">
                <div class="card">
                    <div class="muted">Allocated</div>
                    <div class="stat-value">Rp {{ number_format((float) $summary['allocated'], 0, ',', '.') }}</div>
                    <div class="muted">Total anggaran</div>
                </div>
                <div class="card">
                    <div class="muted">Committed</div>
                    <div class="stat-value">Rp {{ number_format((float) $summary['committed'], 0, ',', '.') }}</div>
                    <div class="muted">PR submitted / belum actual</div>
                </div>
                <div class="card">
                    <div class="muted">Actual</div>
                    <div class="stat-value">Rp {{ number_format((float) $summary['actual'], 0, ',', '.') }}</div>
                    <div class="muted">Sudah terealisasi</div>
                </div>
                <div class="card">
                    <div class="muted">Remaining</div>
                    <div class="stat-value">Rp {{ number_format((float) $summary['remaining'], 0, ',', '.') }}</div>
                    <div class="muted">{{ number_format((float) $summary['used_percent'], 1, ',', '.') }}% used - {{ $summary['watch_count'] }} perlu perhatian</div>
                </div>
            </section>

            <section class="card">
                <div class="toolbar">
                    <div>
                        <h2 style="margin-bottom:6px;">Budget Lines</h2>
                        <p class="muted" style="margin:0;">Kontrol anggaran per account: committed + actual dibandingkan allocated.</p>
                    </div>
                    <span class="badge">{{ number_format($summary['line_count']) }} lines</span>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Departemen</th>
                            <th>Budget</th>
                            <th>Account</th>
                            <th>Allocated</th>
                            <th>Committed</th>
                            <th>Actual</th>
                            <th>Remaining</th>
                            <th>Usage</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($lines as $line)
                            @php
                                $barColor = match ($line->control_status) {
                                    'over' => '#ef4444',
                                    'critical' => '#f97316',
                                    'watch' => '#f59e0b',
                                    default => '#20c997',
                                };
                                $barWidth = min(100, (float) $line->used_percent);
                            @endphp
                            <tr>
                                <td><strong>{{ $line->department_code }}</strong><br><span class="muted">{{ $line->department_name }}</span></td>
                                <td>{{ $line->budget_name }}<br><span class="muted">{{ \Illuminate\Support\Carbon::parse($line->period_start)->format('d M Y') }} - {{ \Illuminate\Support\Carbon::parse($line->period_end)->format('d M Y') }}</span></td>
                                <td><strong>{{ $line->account_code }}</strong><br><span class="muted">{{ $line->description }}</span></td>
                                <td>Rp {{ number_format((float) $line->allocated_amount, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format((float) $line->committed_amount, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format((float) $line->actual_amount, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format((float) $line->remaining_amount, 0, ',', '.') }}</td>
                                <td style="min-width:160px;">
                                    <div style="height:10px;border-radius:999px;background:#eef2ff;overflow:hidden;">
                                        <div style="height:10px;width:{{ $barWidth }}%;background:{{ $barColor }};"></div>
                                    </div>
                                    <div class="muted" style="margin-top:6px;">{{ number_format((float) $line->used_percent, 1, ',', '.') }}%</div>
                                </td>
                                <td><span class="status">{{ $line->control_status }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9"><div class="empty-state">Tidak ada budget line pada filter ini.</div></td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
@endsection
