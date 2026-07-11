@extends('layouts.app', ['title' => __('reports.center.title').' · SAMS'])

@section('body')
    <div class="app-shell">
        @include('partials.sidebar')

        <main class="main">
            <header class="topbar page-intro">
                <div class="page-intro__copy">
                    <p class="eyebrow">SuperSoft Intelligence</p>
                    <h1>{{ __('reports.center.title') }}</h1>
                    <p class="muted">{{ __('reports.center.subtitle') }}</p>
                </div>

                <a class="button secondary inline" href="{{ route('dashboard') }}">{{ __('common.actions.back') }}</a>
            </header>

            <section class="grid stats" style="margin-bottom:18px;">
                <div class="card metric-card">
                    <div class="muted">{{ __('reports.metrics.total_reports') }}</div>
                    <div class="stat-value">{{ number_format($summary['ready_reports']) }}</div>
                    <div class="muted">{{ __('reports.center.available_reports') }}</div>
                </div>
                <div class="card metric-card">
                    <div class="muted">{{ __('reports.metrics.export_ready') }}</div>
                    <div class="stat-value">{{ number_format($summary['export_ready']) }}</div>
                    <div class="muted">CSV / Excel</div>
                </div>
                <div class="card metric-card">
                    <div class="muted">{{ __('reports.metrics.print_ready') }}</div>
                    <div class="stat-value">{{ number_format($summary['print_ready']) }}</div>
                    <div class="muted">Print / PDF</div>
                </div>
                <div class="card metric-card {{ $summary['control_alerts'] > 0 ? 'warning' : '' }}">
                    <div class="muted">{{ __('reports.metrics.control_alerts') }}</div>
                    <div class="stat-value">{{ number_format($summary['control_alerts']) }}</div>
                    <div class="muted">{{ __('reports.metrics.needs_attention') }}</div>
                </div>
            </section>

            <section class="card" style="margin-bottom:18px;">
                <div class="toolbar section-heading">
                    <div>
                        <h2 style="margin-bottom:6px;">{{ __('reports.center.available_reports') }}</h2>
                        <p class="muted" style="margin:0;">{{ __('reports.center.catalogue_description') }}</p>
                    </div>
                    <span class="badge">{{ __('reports.center.ai_ready') }}</span>
                </div>

                <div class="report-grid">
                    @foreach ($reports as $report)
                        <article class="quick-action report-card">
                            <div class="report-card__head">
                                <span class="report-card__icon"><x-icon name="reports" /></span>
                                <span class="badge">{{ $report['badge'] }}</span>
                            </div>

                            <div class="report-card__body">
                                <p class="eyebrow">{{ __('reports.categories.'.$report['category_key']) }}</p>
                                <h3 style="margin-bottom:8px;">{{ __('reports.names.'.$report['name_key']) }}</h3>
                                <p class="muted">{{ __('reports.descriptions.'.$report['name_key']) }}</p>
                            </div>

                            <div class="report-card__actions">
                                <a class="button inline" href="{{ route($report['route']) }}">{{ __('common.actions.open') }}</a>
                                @if ($report['print_route'])
                                    <a class="button secondary inline" href="{{ route($report['print_route']) }}" target="_blank" rel="noopener">{{ __('common.actions.print') }}</a>
                                @endif
                                <a class="button secondary inline" href="{{ route($report['export_route']) }}">{{ __('common.actions.export_csv') }}</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="card">
                <div class="toolbar section-heading">
                    <div>
                        <h2 style="margin-bottom:6px;">{{ __('reports.intelligence.title') }}</h2>
                        <p class="muted" style="margin:0;line-height:1.7;">{{ __('reports.intelligence.description') }}</p>
                    </div>
                    <span class="badge next">{{ __('reports.intelligence.upcoming') }}</span>
                </div>

                <div class="module-list">
                    @foreach (['executive_summary', 'template_control', 'scheduled_reports'] as $intelligenceItem)
                        <div class="module-row">
                            <div>
                                <strong>{{ __('reports.intelligence.items.'.$intelligenceItem.'.title') }}</strong>
                                <p class="muted" style="margin:6px 0 0;line-height:1.6;">{{ __('reports.intelligence.items.'.$intelligenceItem.'.description') }}</p>
                            </div>
                            <span class="badge next">{{ __('reports.intelligence.planned') }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        </main>
    </div>
@endsection
