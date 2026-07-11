@extends('layouts.app', ['title' => 'Report Center - SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Control Tower</p>
                    <h1>Report Center</h1>
                    <p class="muted" style="margin:8px 0 0;max-width:760px;line-height:1.7;">Pusat laporan SAMS untuk print, export, dan kontrol operasional. Area ini disiapkan sebagai fondasi AI reporting berikutnya.</p>
                </div>

                <a class="button secondary inline" href="{{ route('dashboard') }}">Back to Dashboard</a>
            </header>

            <section class="grid stats" style="margin-bottom:18px;">
                <div class="card">
                    <div class="muted">Ready Reports</div>
                    <div class="stat-value">{{ number_format($summary['ready_reports']) }}</div>
                    <div class="muted">Laporan aktif</div>
                </div>
                <div class="card">
                    <div class="muted">Export Ready</div>
                    <div class="stat-value">{{ number_format($summary['export_ready']) }}</div>
                    <div class="muted">CSV untuk Excel</div>
                </div>
                <div class="card">
                    <div class="muted">Print Ready</div>
                    <div class="stat-value">{{ number_format($summary['print_ready']) }}</div>
                    <div class="muted">Dokumen print/PDF</div>
                </div>
                <div class="card">
                    <div class="muted">Control Alerts</div>
                    <div class="stat-value">{{ number_format($summary['control_alerts']) }}</div>
                    <div class="muted">Perlu perhatian</div>
                </div>
            </section>

            <section class="card" style="margin-bottom:18px;">
                <div class="toolbar">
                    <div>
                        <h2 style="margin-bottom:6px;">Available Reports</h2>
                        <p class="muted" style="margin:0;">Setiap laporan dibuat dengan jalur operasional: lihat data, print dokumen, lalu export untuk analisa lanjutan.</p>
                    </div>
                    <span class="badge">AI-ready foundation</span>
                </div>

                <div class="report-grid">
                    @foreach ($reports as $report)
                        <article class="quick-action" style="display:grid;gap:14px;">
                            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:14px;">
                                <div>
                                    <p class="eyebrow">{{ $report['category'] }}</p>
                                    <h3 style="margin-bottom:8px;">{{ $report['title'] }}</h3>
                                    <p class="muted" style="margin:0;line-height:1.7;">{{ $report['description'] }}</p>
                                </div>
                                <span class="badge">{{ $report['badge'] }}</span>
                            </div>

                            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                <a class="button inline" href="{{ route($report['route']) }}">Open</a>
                                @if ($report['print_route'])
                                    <a class="button secondary inline" href="{{ route($report['print_route']) }}" target="_blank">Print</a>
                                @endif
                                <a class="button secondary inline" href="{{ route($report['export_route']) }}">Export CSV</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="card">
                <div class="toolbar">
                    <div>
                        <h2 style="margin-bottom:6px;">Next Intelligence Layer</h2>
                        <p class="muted" style="margin:0;line-height:1.7;">Tahap berikutnya: ringkasan otomatis, anomaly watch, supplier risk note, budget burn insight, dan rekomendasi action.</p>
                    </div>
                    <span class="badge next">Upcoming</span>
                </div>

                <div class="module-list">
                    <div class="module-row">
                        <div>
                            <strong>AI Executive Summary</strong>
                            <p class="muted" style="margin:6px 0 0;line-height:1.6;">Narasi otomatis dari data purchasing, budget, inventory, dan maintenance.</p>
                        </div>
                        <span class="badge next">Planned</span>
                    </div>
                    <div class="module-row">
                        <div>
                            <strong>Report Template Control</strong>
                            <p class="muted" style="margin:6px 0 0;line-height:1.6;">Standarisasi print seperti dokumen enterprise: nomor, periode, approval, footer, dan signature.</p>
                        </div>
                        <span class="badge next">Planned</span>
                    </div>
                    <div class="module-row">
                        <div>
                            <strong>Saved Filters & Scheduled Reports</strong>
                            <p class="muted" style="margin:6px 0 0;line-height:1.6;">Filter favorit, export rutin, dan kesiapan pengiriman laporan saat SAMS masuk VPS.</p>
                        </div>
                        <span class="badge next">Planned</span>
                    </div>
                </div>
            </section>
        </main>
    </div>
@endsection
