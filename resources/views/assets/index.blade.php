@extends('layouts.app', ['title' => 'Asset Register - SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Asset Management</p>
                    <h1>Asset Register</h1>
                </div>

                @if (auth()->user()->hasAnyRole(['super_admin', 'purchasing', 'warehouse']))
                    <a class="button inline" href="{{ route('assets.create') }}">Tambah Asset</a>
                @endif
            </header>

            @if (session('status'))
                <div class="notice">{{ session('status') }}</div>
            @endif

            <section class="card" style="margin-bottom:18px;">
                <form method="GET" action="{{ route('assets.index') }}">
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
                            <span class="label">Kondisi</span>
                            <select class="input" name="condition">
                                <option value="">Semua kondisi</option>
                                @foreach ($conditions as $value => $label)
                                    <option value="{{ $value }}" @selected($filters['condition'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="field full">
                            <span class="label">Departemen</span>
                            <select class="input" name="department_id">
                                <option value="">Semua departemen</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected((int) $filters['department_id'] === (int) $department->id)>{{ $department->code }} - {{ $department->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:10px;">
                        <a class="button secondary inline" href="{{ route('assets.index') }}">Reset</a>
                        <button class="button inline" type="submit">Tampilkan</button>
                    </div>
                </form>
            </section>

            <section class="grid stats" style="margin-bottom:18px;">
                <div class="card">
                    <div class="muted">Total Asset</div>
                    <div class="stat-value">{{ number_format($summary['asset_count']) }}</div>
                    <div class="muted">Semua asset terdaftar</div>
                </div>
                <div class="card">
                    <div class="muted">Active</div>
                    <div class="stat-value">{{ number_format($summary['active_count']) }}</div>
                    <div class="muted">Asset masih digunakan</div>
                </div>
                <div class="card">
                    <div class="muted">Watch</div>
                    <div class="stat-value">{{ number_format($summary['watch_count']) }}</div>
                    <div class="muted">Kondisi fair / poor</div>
                </div>
                <div class="card">
                    <div class="muted">Acquisition Value</div>
                    <div class="stat-value">Rp {{ number_format((float) $summary['total_cost'], 0, ',', '.') }}</div>
                    <div class="muted">Nilai perolehan</div>
                </div>
            </section>

            <section class="card">
                <div class="toolbar">
                    <div>
                        <h2 style="margin-bottom:6px;">Asset Listing</h2>
                        <p class="muted" style="margin:0;">Daftar aset aktif, lokasi, kondisi, dan nilai perolehan.</p>
                    </div>
                    <span class="badge">{{ $assets->total() }} assets</span>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Asset</th>
                            <th>Item</th>
                            <th>Departemen</th>
                            <th>Lokasi</th>
                            <th>Tanggal</th>
                            <th>Nilai</th>
                            <th>Kondisi</th>
                            <th>Status</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($assets as $asset)
                            <tr>
                                <td><strong>{{ $asset->asset_number }}</strong><br><span class="muted">{{ $asset->asset_name }}</span></td>
                                <td><strong>{{ $asset->sku }}</strong><br><span class="muted">{{ $asset->item_name }}</span></td>
                                <td>{{ $asset->department_code ? $asset->department_code.' - '.$asset->department_name : '-' }}</td>
                                <td>{{ $asset->location_code ? $asset->location_code.' - '.$asset->location_name : '-' }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($asset->acquisition_date)->format('d M Y') }}</td>
                                <td>Rp {{ number_format((float) $asset->acquisition_cost, 0, ',', '.') }}</td>
                                <td><span class="status">{{ $asset->condition }}</span></td>
                                <td><span class="status">{{ $asset->status }}</span></td>
                                <td>
                                    <div class="actions">
                                        <a class="link-action" href="{{ route('assets.show', $asset->id) }}">Detail</a>
                                        <a class="link-action" href="{{ route('assets.print', $asset->id) }}" target="_blank">Print</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9"><div class="empty-state">Belum ada asset pada filter ini.</div></td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $assets->links() }}
            </section>
        </main>
    </div>
@endsection
