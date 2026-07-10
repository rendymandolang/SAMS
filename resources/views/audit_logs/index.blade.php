@extends('layouts.app', ['title' => 'Audit Trail · SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Control</p>
                    <h1>Audit Trail</h1>
                </div>
            </header>

            <section class="card" style="margin-bottom:18px;">
                <form method="GET" action="{{ route('audit-logs.index') }}">
                    <div class="form-grid">
                        <label class="field">
                            <span class="label">Event</span>
                            <select class="input" name="event">
                                <option value="">Semua event</option>
                                @foreach ($events as $eventOption)
                                    <option value="{{ $eventOption }}" @selected($event === $eventOption)>{{ str_replace('_', ' ', $eventOption) }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="field">
                            <span class="label">Tipe Data</span>
                            <select class="input" name="auditable_type">
                                <option value="">Semua tipe</option>
                                @foreach ($auditableTypes as $typeOption)
                                    <option value="{{ $typeOption }}" @selected($auditableType === $typeOption)>{{ str_replace('_', ' ', $typeOption) }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:10px;">
                        <a class="button secondary inline" href="{{ route('audit-logs.index') }}">Reset</a>
                        <button class="button inline" type="submit">Filter</button>
                    </div>
                </form>
            </section>

            <section class="card">
                <div class="toolbar">
                    <div>
                        <h2 style="margin-bottom:6px;">Riwayat Aktivitas</h2>
                        <p class="muted" style="margin:0;">Jejak perubahan penting pada data master, user, approval, posting, dan dokumen.</p>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>User</th>
                            <th>Event</th>
                            <th>Data</th>
                            <th>Old Values</th>
                            <th>New Values</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($auditLogs as $log)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($log->created_at)->format('d M Y H:i:s') }}</td>
                                <td>
                                    <strong>{{ $log->user_name ?: 'System' }}</strong><br>
                                    <span class="muted">{{ $log->user_email ?: '-' }}</span>
                                </td>
                                <td><span class="status">{{ str_replace('_', ' ', $log->event) }}</span></td>
                                <td>
                                    <strong>{{ str_replace('_', ' ', $log->auditable_type) }}</strong><br>
                                    <span class="muted">ID #{{ $log->auditable_id }}</span>
                                </td>
                                <td><pre style="white-space:pre-wrap;margin:0;font-size:11px;">{{ $log->old_values ?: '-' }}</pre></td>
                                <td><pre style="white-space:pre-wrap;margin:0;font-size:11px;">{{ $log->new_values ?: '-' }}</pre></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">Belum ada audit log.</div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($auditLogs->hasPages())
                    <div class="pagination">{{ $auditLogs->links() }}</div>
                @endif
            </section>
        </main>
    </div>
@endsection
