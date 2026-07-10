<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SAMS' }}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f7fb;
            --card: #ffffff;
            --ink: #172033;
            --muted: #6b7280;
            --line: #e5e7eb;
            --primary: #6259ca;
            --primary-dark: #42389d;
            --accent: #20c997;
            --sidebar: #171a2f;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        a { color: inherit; text-decoration: none; }
        button, input, select, textarea { font: inherit; }

        .auth-shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 32px;
            background:
                radial-gradient(circle at top left, rgba(98, 89, 202, .28), transparent 34%),
                radial-gradient(circle at bottom right, rgba(32, 201, 151, .22), transparent 30%),
                #eef2ff;
        }

        .auth-card {
            width: min(960px, 100%);
            display: grid;
            grid-template-columns: 1fr 420px;
            overflow: hidden;
            border-radius: 28px;
            background: var(--card);
            box-shadow: 0 28px 80px rgba(28, 34, 66, .18);
        }

        .auth-hero {
            padding: 48px;
            color: #fff;
            background: linear-gradient(145deg, #171a2f, #6259ca);
        }

        .auth-form { padding: 48px; }
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
            transition: .2s;
            background: #fff;
        }
        .input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(98, 89, 202, .12); }
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
            box-shadow: 0 14px 30px rgba(98, 89, 202, .25);
        }
        .button:hover { background: var(--primary-dark); }
        .button.secondary {
            width: auto;
            color: var(--ink);
            background: #eef2ff;
            box-shadow: none;
        }
        .button.danger {
            width: auto;
            background: #ef4444;
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
            padding: 24px;
            color: rgba(255,255,255,.76);
            background: var(--sidebar);
        }
        .sidebar .brand { color: #fff; margin-bottom: 34px; }
        .nav-group { display: grid; gap: 8px; margin-top: 18px; }
        .nav-title {
            margin: 24px 0 8px;
            color: rgba(255,255,255,.42);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .1em;
            text-transform: uppercase;
        }
        .nav-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-radius: 14px;
            padding: 12px 14px;
            color: rgba(255,255,255,.74);
        }
        .nav-link.active, .nav-link:hover { color: #fff; background: rgba(255,255,255,.1); }
        .main { padding: 28px; }
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
            background: #fff;
        }
        .logout {
            border: 0;
            border-radius: 999px;
            padding: 8px 12px;
            color: #fff;
            background: #ef4444;
            cursor: pointer;
            font-weight: 700;
        }
        .grid { display: grid; gap: 18px; }
        .stats { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .content-grid { grid-template-columns: 1.5fr .9fr; margin-top: 18px; }
        .card {
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 22px;
            background: var(--card);
            box-shadow: 0 12px 28px rgba(17, 24, 39, .05);
        }
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
            background: #fbfdff;
        }
        .badge {
            align-self: start;
            border-radius: 999px;
            padding: 6px 10px;
            color: #0f766e;
            background: #ccfbf1;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }
        .badge.next { color: #92400e; background: #fef3c7; }
        .quick-actions { display: grid; gap: 12px; }
        .quick-action {
            border: 1px dashed #c7d2fe;
            border-radius: 16px;
            padding: 14px;
            background: #f8faff;
        }
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 18px;
        }
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
            background: #eef2ff;
            cursor: pointer;
            font-weight: 800;
        }
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
            background: #fbfdff;
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
            background: #fbfdff;
        }
        .line-card.budgeted {
            grid-template-columns: 1.4fr 1.1fr .55fr .75fr .9fr;
        }
        .status {
            display: inline-flex;
            border-radius: 999px;
            padding: 6px 10px;
            background: #eef2ff;
            color: var(--primary);
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
        }

        @media (max-width: 980px) {
            .auth-card, .app-shell, .content-grid { grid-template-columns: 1fr; }
            .auth-hero { display: none; }
            .sidebar { position: static; }
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .line-card, .line-card.budgeted { grid-template-columns: 1fr; }
        }

        @media (max-width: 620px) {
            .main, .sidebar, .auth-form { padding: 22px; }
            .stats { grid-template-columns: 1fr; }
            .detail-grid { grid-template-columns: 1fr; }
            .topbar { align-items: flex-start; flex-direction: column; }
        }
    </style>
</head>
<body>
    @yield('body')
</body>
</html>
