@extends('layouts.app', ['title' => 'Enterprise & Storage · '.config('supersoft.product_name')])

@section('body')
<div class="app-shell">
    @include('partials.sidebar')

    <main class="main">
        <header class="topbar page-intro">
            <div class="page-intro__copy">
                <p class="eyebrow">SuperSoft Enterprise Core · {{ $company->name }}</p>
                <h1>License & Data Storage</h1>
                <p class="muted">Status subscription, entitlement modul, kapasitas, dan lokasi penyimpanan perusahaan.</p>
            </div>
        </header>

        @if(session('status'))
            <div class="notice">{{ session('status') }}</div>
        @endif
        @if(session('warning'))
            <div class="error" style="color:#92400e;background:#fef3c7">{{ session('warning') }}</div>
        @endif
        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif
        @if(in_array($storageUsage['level'], ['warning', 'high', 'critical'], true))
            <div class="error" style="color:#92400e;background:#fef3c7">
                Storage telah menggunakan {{ number_format($storageUsage['percentage'], 2, ',', '.') }}% dari kuota.
                @if($storageUsage['level'] === 'critical') Hentikan upload non-prioritas dan tambah kapasitas segera.
                @elseif($storageUsage['level'] === 'high') Siapkan penambahan kapasitas atau arsipkan dokumen lama.
                @else Pantau pertumbuhan penyimpanan perusahaan.@endif
            </div>
        @endif

        <section class="grid stats" style="margin-bottom:18px">
            <div class="card metric-card">
                <div class="muted">Plan</div>
                <div class="stat-value" style="font-size:22px">{{ str($subscription->plan_code)->replace('-', ' ')->title() }}</div>
                <div class="muted">{{ str($subscription->license_model)->title() }}</div>
            </div>
            <div class="card metric-card">
                <div class="muted">Subscription Status</div>
                <div class="stat-value" style="font-size:22px">{{ str($subscription->status)->title() }}</div>
                <div class="muted">{{ str($subscription->billing_cycle)->replace('_', ' ')->title() }}</div>
            </div>
            <div class="card metric-card">
                <div class="muted">Licensed Modules</div>
                <div class="stat-value">{{ $modules->where('is_licensed', true)->count() }}</div>
                <div class="muted">{{ $modules->where('is_enabled', true)->count() }} currently active</div>
            </div>
            <div class="card metric-card">
                <div class="muted">Storage Used</div>
                <div class="stat-value" style="font-size:22px">{{ number_format($storage->used_bytes / 1024 / 1024, 2, ',', '.') }} MB</div>
                <div class="muted">{{ $storage->quota_bytes ? number_format($storage->quota_bytes / 1024 / 1024 / 1024, 0).' GB quota' : 'No quota configured' }}</div>
            </div>
        </section>

        <section class="card" style="margin-bottom:18px">
            <div class="toolbar section-heading">
                <div>
                    <h2>Module Entitlement</h2>
                    <p class="muted">Lisensi ditetapkan oleh SuperSoft. Administrator perusahaan hanya dapat mengaktifkan modul yang telah berlisensi.</p>
                </div>
                <a class="button secondary inline" href="{{ route('access-control.index') }}">Module Activation</a>
            </div>
            <div class="report-grid">
                @foreach($modules as $module)
                    <div class="quick-action">
                        <strong>{{ $module->name }}</strong>
                        <p class="muted" style="margin:8px 0 12px">{{ $module->licensed_until ? 'Licensed until '.$module->licensed_until : 'No expiry configured' }}</p>
                        <span class="badge {{ $module->is_licensed ? '' : 'next' }}">{{ $module->is_licensed ? ($module->is_enabled ? 'Licensed · Active' : 'Licensed · Inactive') : 'Not licensed' }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card" style="margin-bottom:18px">
            <div class="toolbar section-heading">
                <div>
                    <h2>Data Storage</h2>
                    <p class="muted">Credential BYOC dienkripsi dan tidak pernah ditampilkan kembali setelah disimpan.</p>
                </div>
                <span class="badge {{ in_array($storage->status, ['failed', 'pending_test', 'pending_connector'], true) ? 'next' : '' }}">{{ str($storage->status)->replace('_', ' ')->title() }}</span>
            </div>

            @if($storage->last_test_message)
                <div class="detail-box" style="margin-bottom:18px">
                    <strong>Last connection test</strong>
                    <div class="muted" style="margin-top:5px">{{ $storage->last_test_message }} · {{ $storage->last_tested_at }}</div>
                </div>
            @endif

            <form method="POST" action="{{ route('settings.enterprise.storage.update') }}">
                @csrf
                @method('PUT')
                <div class="form-grid">
                    <label class="field">
                        <span class="label">Storage Mode</span>
                        <select class="input" name="mode" required>
                            <option value="local" @selected(old('mode', $storage->mode) === 'local')>Local / On-Premise</option>
                            <option value="byoc" @selected(old('mode', $storage->mode) === 'byoc')>Bring Your Own Cloud</option>
                            <option value="managed" @selected(old('mode', $storage->mode) === 'managed')>SuperSoft Managed Cloud</option>
                        </select>
                    </label>
                    <label class="field">
                        <span class="label">Provider</span>
                        <select class="input" name="provider" required>
                            <option value="local" @selected(old('provider', $storage->provider) === 'local')>Local Private Storage</option>
                            <option value="s3_compatible" @selected(old('provider', $storage->provider) === 's3_compatible')>S3-Compatible Cloud</option>
                            <option value="supersoft_cloud" @selected(old('provider', $storage->provider) === 'supersoft_cloud')>SuperSoft Cloud</option>
                        </select>
                    </label>
                    <label class="field">
                        <span class="label">Bucket</span>
                        <input class="input" name="bucket" value="{{ old('bucket', $storage->bucket) }}" autocomplete="off">
                    </label>
                    <label class="field">
                        <span class="label">Region</span>
                        <input class="input" name="region" value="{{ old('region', $storage->region) }}" autocomplete="off">
                    </label>
                    <label class="field full">
                        <span class="label">Endpoint URL</span>
                        <input class="input" type="url" name="endpoint" value="{{ old('endpoint', $storage->endpoint) }}" placeholder="https://storage.example.com" autocomplete="off">
                    </label>
                    <label class="field">
                        <span class="label">Access Key</span>
                        <input class="input" type="password" name="access_key" value="" autocomplete="new-password" placeholder="Kosongkan untuk mempertahankan credential">
                    </label>
                    <label class="field">
                        <span class="label">Secret Key</span>
                        <input class="input" type="password" name="secret_key" value="" autocomplete="new-password" placeholder="Tidak pernah ditampilkan kembali">
                    </label>
                    <label class="field">
                        <span class="label">Storage Quota (GB)</span>
                        <input class="input" type="number" min="1" max="1048576" name="storage_quota_gb" value="{{ old('storage_quota_gb', $storage->quota_bytes ? (int) ($storage->quota_bytes / 1024 / 1024 / 1024) : '') }}">
                    </label>
                    <label class="field" style="align-content:center">
                        <span class="label">S3 Addressing</span>
                        <span style="display:flex;align-items:center;gap:8px">
                            <input type="checkbox" name="use_path_style_endpoint" value="1" @checked(old('use_path_style_endpoint', $storage->use_path_style_endpoint))>
                            Gunakan path-style endpoint
                        </span>
                    </label>
                </div>
                <button class="button inline" type="submit">Save Storage Configuration</button>
            </form>

            <form method="POST" action="{{ route('settings.enterprise.storage.test') }}" style="margin-top:12px">
                @csrf
                <button class="button secondary inline" type="submit">Test Current Connection</button>
            </form>
        </section>

        <section class="card">
            <div class="toolbar section-heading">
                <div>
                    <h2>Encrypted Company Backup</h2>
                    <p class="muted">Snapshot data perusahaan dienkripsi sebelum disimpan. Setiap backup langsung diuji melalui dekripsi, checksum, dan validasi struktur.</p>
                </div>
                <form method="POST" action="{{ route('settings.enterprise.backups.store') }}">
                    @csrf
                    <button class="button inline" type="submit">Create & Verify Backup</button>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead><tr><th>Created</th><th>Size</th><th>Tables / Rows</th><th>Status</th><th>Verification</th><th></th></tr></thead>
                    <tbody>
                        @forelse($backups as $backup)
                            <tr>
                                <td>{{ $backup->created_at }}<br><span class="muted">{{ $backup->creator_name }}</span></td>
                                <td>{{ number_format($backup->size_bytes / 1024, 2, ',', '.') }} KB</td>
                                <td>{{ $backup->table_count }} / {{ number_format($backup->row_count, 0, ',', '.') }}</td>
                                <td><span class="badge">{{ str($backup->status)->replace('_', ' ')->title() }}</span></td>
                                <td>{{ $backup->verification_message ?: 'Belum diverifikasi' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('settings.enterprise.backups.verify', $backup->id) }}">
                                        @csrf
                                        <button class="button secondary inline" type="submit">Verify Again</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6">Belum ada backup perusahaan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
@endsection
