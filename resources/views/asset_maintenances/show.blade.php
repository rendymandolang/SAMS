@extends('layouts.app', ['title' => $maintenance->document_number.' - SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Maintenance Work Order</p>
                    <h1>{{ $maintenance->document_number }}</h1>
                </div>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <a class="button secondary inline" href="{{ route('asset-maintenances.index') }}">Kembali</a>
                    <a class="button secondary inline" href="{{ route('asset-maintenances.print', $maintenance->id) }}" target="_blank">Print WO</a>
                </div>
            </header>

            @if (session('status'))
                <div class="notice">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif

            <section class="card" style="margin-bottom:18px;">
                <div class="detail-grid">
                    <div class="detail-box"><div class="muted">Status</div><div class="value"><span class="status">{{ $maintenance->status }}</span></div></div>
                    <div class="detail-box"><div class="muted">Priority</div><div class="value"><span class="status">{{ $maintenance->priority }}</span></div></div>
                    <div class="detail-box"><div class="muted">Type</div><div class="value">{{ $maintenance->maintenance_type }}</div></div>
                    <div class="detail-box"><div class="muted">Asset</div><div class="value">{{ $maintenance->asset_number }}</div></div>
                    <div class="detail-box"><div class="muted">Request Date</div><div class="value">{{ \Illuminate\Support\Carbon::parse($maintenance->request_date)->format('d M Y') }}</div></div>
                    <div class="detail-box"><div class="muted">Scheduled</div><div class="value">{{ $maintenance->scheduled_date ? \Illuminate\Support\Carbon::parse($maintenance->scheduled_date)->format('d M Y') : '-' }}</div></div>
                    <div class="detail-box"><div class="muted">Estimated Cost</div><div class="value">Rp {{ number_format((float) $maintenance->estimated_cost, 0, ',', '.') }}</div></div>
                    <div class="detail-box"><div class="muted">Actual Cost</div><div class="value">Rp {{ number_format((float) $maintenance->actual_cost, 0, ',', '.') }}</div></div>
                </div>

                <div class="muted">Issue Description</div>
                <p style="margin:8px 0 18px;line-height:1.7;">{{ $maintenance->issue_description }}</p>

                <div class="muted">Resolution Notes</div>
                <p style="margin:8px 0 0;line-height:1.7;">{{ $maintenance->resolution_notes ?: '-' }}</p>
            </section>

            @if ($maintenance->status !== 'completed' && auth()->user()->hasAnyRole(['super_admin', 'purchasing', 'warehouse']))
                <section class="card">
                    <h2>Complete Maintenance</h2>
                    <form method="POST" action="{{ route('asset-maintenances.complete', $maintenance->id) }}">
                        @csrf
                        <div class="form-grid">
                            <label class="field"><span class="label">Completed Date</span><input class="input" name="completed_date" type="date" value="{{ now()->format('Y-m-d') }}" required></label>
                            <label class="field"><span class="label">Actual Cost</span><input class="input" name="actual_cost" type="number" min="0" step="0.01" value="0"></label>
                            <label class="field"><span class="label">Asset Condition</span><select class="input" name="asset_condition"><option value="good">Good</option><option value="fair">Fair</option><option value="poor">Poor</option><option value="repair">Repair</option></select></label>
                            <label class="field"><span class="label">Asset Status</span><select class="input" name="asset_status"><option value="active">Active</option><option value="maintenance">Maintenance</option><option value="retired">Retired</option><option value="lost">Lost</option></select></label>
                            <label class="field full"><span class="label">Resolution Notes</span><textarea class="input" name="resolution_notes" required placeholder="Tindakan perbaikan / hasil inspeksi"></textarea></label>
                        </div>
                        <div style="display:flex;justify-content:flex-end;margin-top:12px;">
                            <button class="button inline" type="submit">Complete WO</button>
                        </div>
                    </form>
                </section>
            @endif
        </main>
    </div>
@endsection
