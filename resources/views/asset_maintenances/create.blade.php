@extends('layouts.app', ['title' => 'Buat Asset Maintenance - SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Asset Maintenance</p>
                    <h1>Buat Work Order</h1>
                </div>
                <a class="button secondary inline" href="{{ route('assets.show', $asset->id) }}">Kembali</a>
            </header>

            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif

            <section class="card" style="margin-bottom:18px;">
                <div class="detail-grid" style="margin-bottom:0;">
                    <div class="detail-box"><div class="muted">Asset</div><div class="value">{{ $asset->asset_number }}</div></div>
                    <div class="detail-box"><div class="muted">Nama</div><div class="value">{{ $asset->asset_name }}</div></div>
                    <div class="detail-box"><div class="muted">Kondisi</div><div class="value">{{ $asset->condition }}</div></div>
                    <div class="detail-box"><div class="muted">Lokasi</div><div class="value">{{ $asset->location_code ?: '-' }}</div></div>
                </div>
            </section>

            <section class="card">
                <form method="POST" action="{{ route('asset-maintenances.store', $asset->id) }}">
                    @csrf
                    <div class="form-grid">
                        <label class="field">
                            <span class="label">Maintenance Type</span>
                            <select class="input" name="maintenance_type" required>
                                @foreach ($types as $value => $label)
                                    <option value="{{ $value }}" @selected(old('maintenance_type', 'corrective') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="field">
                            <span class="label">Priority</span>
                            <select class="input" name="priority" required>
                                @foreach ($priorities as $value => $label)
                                    <option value="{{ $value }}" @selected(old('priority', 'normal') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="field">
                            <span class="label">Request Date</span>
                            <input class="input" name="request_date" type="date" value="{{ old('request_date', now()->format('Y-m-d')) }}" required>
                        </label>

                        <label class="field">
                            <span class="label">Scheduled Date</span>
                            <input class="input" name="scheduled_date" type="date" value="{{ old('scheduled_date') }}">
                        </label>

                        <label class="field">
                            <span class="label">Vendor</span>
                            <input class="input" name="vendor_name" value="{{ old('vendor_name') }}" placeholder="Opsional">
                        </label>

                        <label class="field">
                            <span class="label">Estimated Cost</span>
                            <input class="input" name="estimated_cost" type="number" min="0" step="0.01" value="{{ old('estimated_cost', 0) }}">
                        </label>

                        <label class="field full">
                            <span class="label">Issue Description</span>
                            <textarea class="input" name="issue_description" required placeholder="Jelaskan kerusakan, inspeksi, atau kebutuhan maintenance">{{ old('issue_description') }}</textarea>
                        </label>
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:12px;">
                        <a class="button secondary inline" href="{{ route('assets.show', $asset->id) }}">Batal</a>
                        <button class="button inline" type="submit">Buat Work Order</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
@endsection
