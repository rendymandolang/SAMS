@php
    $themeCompany = auth()->check() ? app(\App\Support\CompanyContext::class)->current() : null;
    $safeColor = static fn ($value, $fallback) => is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? strtoupper($value) : $fallback;
    $themePrimary = $safeColor($themeCompany?->primary_color ?? null, '#5967D8');
    $themeAccent = $safeColor($themeCompany?->accent_color ?? null, '#2F9D8F');
    $themeSidebar = $safeColor($themeCompany?->sidebar_color ?? null, '#182335');
    $appName = $themeCompany?->name ?: config('supersoft.product_name');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $themePrimary }}">
    <title>{{ $title ?? $appName.' · '.config('supersoft.product_name') }}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f5f7fb;
            --card: #ffffff;
            --ink: #172033;
            --muted: #667085;
            --line: #e6eaf0;
            --primary: {{ $themePrimary }};
            --primary-dark: color-mix(in srgb, var(--primary) 82%, #101828);
            --primary-soft: color-mix(in srgb, var(--primary) 10%, #ffffff);
            --primary-line: color-mix(in srgb, var(--primary) 20%, #ffffff);
            --accent: {{ $themeAccent }};
            --accent-soft: color-mix(in srgb, var(--accent) 12%, #ffffff);
            --sidebar: {{ $themeSidebar }};
            --sidebar-soft: color-mix(in srgb, var(--sidebar) 88%, #ffffff);
            --surface-muted: #f8fafc;
            --success: #17836f;
            --warning: #b7791f;
            --danger: #d04444;
            --shadow-soft: 0 1px 2px rgba(16, 24, 40, .04), 0 10px 28px rgba(16, 24, 40, .055);
            --shadow-hover: 0 2px 4px rgba(16, 24, 40, .05), 0 16px 36px rgba(16, 24, 40, .08);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at 96% 0%, color-mix(in srgb, var(--primary) 6%, transparent), transparent 30%),
                var(--bg);
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        a { color: inherit; text-decoration: none; }
        button, input, select, textarea { font: inherit; }
        ::selection { color: #fff; background: var(--primary); }
        :focus-visible { outline: 3px solid color-mix(in srgb, var(--primary) 32%, transparent); outline-offset: 2px; }
        .skip-link { position: fixed; z-index: 1000; top: 12px; left: 12px; transform: translateY(-160%); border-radius: 10px; padding: 10px 14px; color: #fff; background: var(--primary); font-weight: 800; }
        .skip-link:focus { transform: translateY(0); }

        .auth-shell {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 24px 28px;
            background:
                radial-gradient(circle at 50% -15%, color-mix(in srgb, var(--primary) 12%, transparent), transparent 38%),
                #f7f8fb;
        }

        .auth-card {
            width: min(440px, 100%);
            border: 1px solid #e2e6ec;
            border-radius: 16px;
            background: var(--card);
            box-shadow: 0 16px 44px rgba(29, 40, 64, .09);
        }

        .auth-form { padding: 42px 42px 36px; }
        .auth-footer { width: min(760px, 100%); margin-top: 26px; text-align: center; color: var(--muted); font-size: 12px; }
        .auth-links { display: flex; justify-content: center; flex-wrap: wrap; gap: 8px 18px; margin-bottom: 15px; }
        .auth-links a:hover { color: var(--primary); text-decoration: underline; }
        .auth-meta { line-height: 1.7; }
        .auth-contact { margin-top: 5px; }
        .auth-contact a { color: var(--primary-dark); font-weight: 700; }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            letter-spacing: .08em;
        }
        .brand-mark {
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border-radius: 12px;
            background: linear-gradient(145deg, var(--primary), var(--accent));
            color: #fff;
            font-weight: 900;
        }

        .field { display: grid; gap: 8px; margin-bottom: 18px; }
        .label { color: #374151; font-size: 14px; font-weight: 700; }
        .input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 13px 15px;
            outline: none;
            transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
            background: rgba(255,255,255,.9);
        }
        .input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary) 12%, transparent); }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            border: 0;
            border-radius: 14px;
            padding: 13px 18px;
            color: #fff;
            background: var(--primary);
            cursor: pointer;
            font-weight: 800;
            box-shadow: 0 10px 24px color-mix(in srgb, var(--primary) 22%, transparent);
            transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
        }
        .button:hover { background: var(--primary-dark); box-shadow: 0 14px 32px color-mix(in srgb, var(--primary) 25%, transparent); transform: translateY(-1px); }
        .button.secondary {
            width: auto;
            color: var(--ink);
            background: var(--primary-soft);
            box-shadow: none;
        }
        .button.danger {
            width: auto;
            background: var(--danger);
            box-shadow: none;
        }
        .button.inline { width: auto; padding: 10px 14px; }
        .error {
            margin: 0 0 18px;
            padding: 12px 14px;
            border-radius: 14px;
            color: #991b1b;
            background: #fee2e2;
            font-size: 14px;
        }

        .app-shell {
            display: grid;
            grid-template-columns: 280px 1fr;
            min-height: 100vh;
        }

        .sidebar {
            position: sticky;
            top: 0;
            display: flex;
            flex-direction: column;
            height: 100dvh;
            overflow-y: auto;
            padding: 22px 18px;
            color: rgba(255,255,255,.76);
            background:
                linear-gradient(180deg, rgba(255,255,255,.045), transparent 24%),
                radial-gradient(circle at top left, color-mix(in srgb, var(--primary) 24%, transparent), transparent 34%),
                var(--sidebar);
            box-shadow: inset -1px 0 0 rgba(255,255,255,.08);
        }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-thumb { border-radius: 999px; background: rgba(255,255,255,.16); }
        .sidebar .brand { min-width: 0; color: #fff; margin: 2px 6px 24px; }
        .brand-logo { width: 40px; height: 40px; flex: 0 0 40px; object-fit: contain; border-radius: 12px; background: #fff; padding: 4px; }
        .brand-copy { min-width: 0; display: grid; gap: 2px; }
        .brand-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 14px; letter-spacing: .02em; }
        .brand-product { color: rgba(255,255,255,.56); font-size: 10px; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; }
        .sidebar-nav { display: grid; gap: 7px; }
        .nav-group { display: grid; gap: 5px; margin: 0; }
        .nav-title {
            margin: 18px 10px 7px;
            color: rgba(255,255,255,.62);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 11px;
            min-height: 44px;
            border-radius: 12px;
            padding: 10px 12px;
            color: rgba(255,255,255,.72);
            font-size: 13px;
            font-weight: 650;
            transition: background .2s ease, color .2s ease, transform .2s ease;
        }
        .nav-link .nav-icon, .nav-section-summary .nav-icon { width: 19px; height: 19px; flex: 0 0 19px; opacity: .86; }
        .nav-link span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .nav-link.active, .nav-link:hover { color: #fff; background: rgba(255,255,255,.105); transform: translateX(1px); }
        .nav-link.active { box-shadow: inset 3px 0 0 var(--accent); background: rgba(255,255,255,.13); }
        .nav-section { border-radius: 14px; }
        .nav-section summary { list-style: none; }
        .nav-section summary::-webkit-details-marker { display: none; }
        .nav-section-summary {
            display: flex;
            align-items: center;
            gap: 11px;
            min-height: 44px;
            border-radius: 12px;
            padding: 10px 12px;
            color: rgba(255,255,255,.72);
            cursor: pointer;
            font-size: 13px;
            font-weight: 750;
            user-select: none;
        }
        .nav-section-summary:hover { color: #fff; background: rgba(255,255,255,.08); }
        .nav-section-summary .chevron { width: 15px; height: 15px; margin-left: auto; transition: transform .2s ease; }
        .nav-section[open] .nav-section-summary .chevron { transform: rotate(180deg); }
        .nav-submenu { display: grid; gap: 3px; margin: 3px 0 5px 18px; padding-left: 12px; border-left: 1px solid rgba(255,255,255,.12); }
        .nav-submenu .nav-link { min-height: 38px; padding: 8px 10px; font-size: 12px; }
        .sidebar-spacer { flex: 1; min-height: 20px; }
        .sidebar-account { margin-top: 22px; border: 1px solid rgba(255,255,255,.1); border-radius: 16px; padding: 12px; background: rgba(255,255,255,.055); }
        .company-chip { display: flex; align-items: center; gap: 9px; min-width: 0; color: #fff; }
        .company-chip__mark { display: grid; width: 31px; height: 31px; flex: 0 0 31px; place-items: center; border-radius: 9px; color: #fff; background: linear-gradient(145deg,var(--primary),var(--accent)); font-weight: 900; }
        .company-chip__copy { min-width: 0; }
        .company-chip__copy strong { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; }
        .company-chip__copy span { display: block; margin-top: 2px; color: rgba(255,255,255,.54); font-size: 10px; }
        .company-select { width: 100%; margin-top: 10px; border: 1px solid rgba(255,255,255,.14); border-radius: 10px; padding: 8px 9px; color: #fff; background: var(--sidebar-soft); font-size: 11px; }
        .sidebar-account__row { display: flex; align-items: center; justify-content: space-between; gap: 9px; margin-top: 12px; padding-top: 11px; border-top: 1px solid rgba(255,255,255,.09); }
        .locale-switch { display: inline-flex; border-radius: 9px; padding: 2px; background: rgba(255,255,255,.08); }
        .locale-switch a { border-radius: 7px; padding: 5px 7px; color: rgba(255,255,255,.58); font-size: 10px; font-weight: 900; }
        .locale-switch a.active { color: #fff; background: rgba(255,255,255,.14); }
        .sidebar-logout { border: 0; padding: 6px 3px; color: rgba(255,255,255,.66); background: transparent; cursor: pointer; font-size: 11px; font-weight: 750; }
        .sidebar-logout:hover { color: #fff; }
        .mobile-menu-toggle, .sidebar-backdrop { display: none; }
        .main { min-width: 0; padding: 30px; }
        .main > * { width: min(100%, 1560px); margin-inline: auto; }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 26px;
        }
        .eyebrow { color: var(--muted); font-size: 14px; margin: 0 0 4px; }
        h1, h2, h3, p { margin-top: 0; }
        h1 { margin-bottom: 0; font-size: clamp(26px, 4vw, 36px); }
        .user-pill {
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 8px 10px 8px 14px;
            background: var(--card);
        }
        .logout {
            border: 0;
            border-radius: 999px;
            padding: 8px 12px;
            color: #fff;
            background: var(--danger);
            cursor: pointer;
            font-weight: 700;
        }
        .grid { display: grid; gap: 18px; }
        .two-columns { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .stats { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .content-grid { grid-template-columns: 1.5fr .9fr; margin-top: 18px; }
        .card {
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 22px;
            background: var(--card);
            box-shadow: var(--shadow-soft);
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        }
        .card:hover { border-color: var(--primary-line); }
        .card.interactive:hover { box-shadow: var(--shadow-hover); transform: translateY(-1px); }
        .stat-value { margin: 8px 0 4px; font-size: 32px; font-weight: 900; }
        .muted { color: var(--muted); }
        .module-list { display: grid; gap: 12px; }
        .module-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 14px;
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--surface-muted);
        }
        .badge {
            align-self: start;
            border-radius: 999px;
            padding: 6px 10px;
            color: var(--success);
            background: var(--accent-soft);
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }
        .badge.next { color: #92400e; background: #fef3c7; }
        .quick-actions { display: grid; gap: 12px; }
        .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 330px), 1fr)); gap: 16px; }
        .quick-action {
            border: 1px solid var(--line);
            border-radius: 17px;
            padding: 18px;
            background: var(--card);
            transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
        }
        .quick-action:hover { border-color: var(--primary-line); box-shadow: var(--shadow-soft); transform: translateY(-1px); }
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 18px;
        }
        .page-intro { align-items: flex-start; }
        .page-intro__copy { max-width: 760px; }
        .page-intro__copy .muted { margin: 8px 0 0; line-height: 1.65; }
        .metric-card { position: relative; overflow: hidden; }
        .metric-card::before { position: absolute; inset: 0 auto 0 0; width: 3px; background: var(--primary); content: ''; }
        .metric-card.warning::before { background: var(--warning); }
        .report-card { position: relative; display: flex; min-height: 250px; flex-direction: column; gap: 16px; }
        .report-card::before { position: absolute; inset: 0 18px auto; height: 3px; border-radius: 0 0 999px 999px; background: linear-gradient(90deg,var(--primary),var(--accent)); content: ''; opacity: .72; }
        .report-card__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; }
        .report-card__icon { display: grid; width: 40px; height: 40px; flex: 0 0 40px; place-items: center; border-radius: 12px; color: var(--primary); background: var(--primary-soft); }
        .report-card__icon svg { width: 20px; height: 20px; }
        .report-card__body { flex: 1; }
        .report-card__body p { margin: 0; line-height: 1.65; }
        .report-card__actions { display: flex; gap: 8px; flex-wrap: wrap; padding-top: 14px; border-top: 1px solid var(--line); }
        .report-card__actions .button { box-shadow: none; }
        .section-heading { align-items: flex-start; }
        .table-wrap { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border-bottom: 1px solid var(--line);
            padding: 13px 10px;
            text-align: left;
            vertical-align: top;
        }
        tbody tr { transition: background .18s ease; }
        tbody tr:hover { background: color-mix(in srgb, var(--primary) 4%, transparent); }
        th {
            color: var(--muted);
            font-size: 12px;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .actions {
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: flex-end;
        }
        .link-action {
            border: 0;
            border-radius: 10px;
            padding: 8px 10px;
            color: var(--primary);
            background: var(--primary-soft);
            cursor: pointer;
            font-weight: 800;
            transition: background .2s ease, transform .2s ease;
        }
        .link-action:hover { background: color-mix(in srgb, var(--primary) 16%, #fff); transform: translateY(-1px); }
        .link-action.danger { color: #b91c1c; background: #fee2e2; }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }
        .field.full { grid-column: 1 / -1; }
        textarea.input { min-height: 120px; resize: vertical; }
        .notice {
            margin-bottom: 18px;
            border-radius: 14px;
            padding: 13px 15px;
            color: #0f766e;
            background: #ccfbf1;
            font-weight: 700;
        }
        .empty-state {
            padding: 38px;
            text-align: center;
            color: var(--muted);
        }
        .pagination {
            display: flex;
            gap: 8px;
            margin-top: 18px;
            align-items: center;
            justify-content: flex-end;
        }
        .pagination a, .pagination span {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 8px 11px;
            background: #fff;
        }
        .pagination .active span { color: #fff; background: var(--primary); border-color: var(--primary); }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }
        .detail-box {
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 14px;
            background: var(--surface-muted);
        }
        .detail-box .value {
            margin-top: 6px;
            font-weight: 900;
        }
        .line-items {
            display: grid;
            gap: 12px;
        }
        .line-card {
            display: grid;
            grid-template-columns: 1.5fr .65fr .8fr 1fr;
            gap: 12px;
            align-items: start;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 14px;
            background: var(--surface-muted);
        }
        .line-card.budgeted {
            grid-template-columns: 1.4fr 1.1fr .55fr .75fr .9fr;
        }
        .status {
            display: inline-flex;
            border-radius: 999px;
            padding: 6px 10px;
            background: var(--primary-soft);
            color: var(--primary);
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
        }

        @media (max-width: 1180px) {
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 980px) {
            .app-shell, .content-grid { grid-template-columns: 1fr; }
            .sidebar {
                position: fixed;
                z-index: 80;
                inset: 0 auto 0 0;
                width: min(310px, 88vw);
                transform: translateX(-105%);
                transition: transform .24s ease;
            }
            body.sidebar-open { overflow: hidden; }
            body.sidebar-open .sidebar { transform: translateX(0); }
            .mobile-menu-toggle {
                position: fixed;
                z-index: 70;
                top: 16px;
                left: 16px;
                display: grid;
                width: 44px;
                height: 44px;
                place-items: center;
                border: 1px solid var(--line);
                border-radius: 13px;
                color: var(--ink);
                background: rgba(255,255,255,.94);
                box-shadow: var(--shadow-soft);
                cursor: pointer;
            }
            .mobile-menu-toggle svg { width: 21px; height: 21px; }
            .sidebar-backdrop { position: fixed; z-index: 75; inset: 0; display: block; visibility: hidden; opacity: 0; border: 0; background: rgba(15,23,42,.38); transition: opacity .2s ease, visibility .2s ease; }
            body.sidebar-open .sidebar-backdrop { visibility: visible; opacity: 1; }
            .main { padding-top: 76px; }
            .detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .line-card, .line-card.budgeted { grid-template-columns: 1fr; }
            .two-columns { grid-template-columns: 1fr; }
        }

        @media (max-width: 620px) {
            .main { padding: 76px 18px 24px; }
            .sidebar, .auth-form { padding: 28px 22px; }
            .stats { grid-template-columns: 1fr; }
            .detail-grid { grid-template-columns: 1fr; }
            .topbar { align-items: flex-start; flex-direction: column; }
            .toolbar { align-items: flex-start; }
            .user-pill { width: 100%; justify-content: space-between; border-radius: 16px; }
            .report-card__actions { display: grid; grid-template-columns: 1fr; }
            .report-card__actions .button { width: 100%; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .001ms !important; animation-duration: .001ms !important; animation-iteration-count: 1 !important; }
        }
    </style>
</head>
<body>
    <a class="skip-link" href="#main-content">{{ app()->getLocale() === 'id' ? 'Lewati ke konten' : 'Skip to content' }}</a>
    @yield('body')
    <script>
        (() => {
            const sidebar = document.getElementById('app-sidebar');
            const toggle = document.querySelector('[data-sidebar-toggle]');
            const backdrop = document.querySelector('[data-sidebar-backdrop]');
            const main = document.querySelector('main.main');

            if (main && ! main.id) main.id = 'main-content';
            if (! sidebar || ! toggle || ! backdrop) return;

            const close = () => {
                document.body.classList.remove('sidebar-open');
                toggle.setAttribute('aria-expanded', 'false');
            };

            toggle.addEventListener('click', () => {
                const open = document.body.classList.toggle('sidebar-open');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
            backdrop.addEventListener('click', close);
            sidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', close));
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') close();
            });
        })();
    </script>
</body>
</html>
