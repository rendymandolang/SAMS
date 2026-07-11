<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print Asset Maintenance History</title>
    <style>
        :root { --ink:#111827; --muted:#6b7280; --line:#d1d5db; --soft:#f3f4f6; }
        * { box-sizing: border-box; }
        body { margin:0; color:var(--ink); background:#e5e7eb; font-family:Arial, Helvetica, sans-serif; font-size:10.5px; }
        .screen-toolbar { display:flex; justify-content:flex-end; gap:10px; max-width:297mm; margin:20px auto 10px; }
        .button { border:0; border-radius:8px; padding:10px 14px; color:#fff; background:#6259ca; cursor:pointer; font-weight:700; text-decoration:none; }
        .button.secondary { color:var(--ink); background:#fff; }
        .page { width:297mm; min-height:210mm; margin:0 auto 20px; padding:12mm; background:#fff; box-shadow:0 18px 60px rgba(15,23,42,.18); }
        .header { display:grid; grid-template-columns:1fr auto; gap:24px; border-bottom:2px solid var(--ink); padding-bottom:12px; }
        .brand { display:flex; gap:12px; align-items:flex-start; }
        .brand-mark { display:grid; width:40px; height:40px; place-items:center; border-radius:10px; color:#fff; background:linear-gradient(145deg,#6259ca,#20c997); font-size:21px; font-weight:900; }
        h1,p { margin-top:0; } h1 { margin-bottom:4px; font-size:20px; letter-spacing:.04em; }
        .muted { color:var(--muted); } .doc-title { text-align:right; } .doc-title h1 { margin-bottom:8px; font-size:22px; }
        .summary { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin:14px 0; }
        .box { border:1px solid var(--line); border-radius:8px; padding:9px; }
        .box strong { display:block; margin-top:4px; font-size:14px; }
        table { width:100%; border-collapse:collapse; } th { border:1px solid var(--line); padding:6px; background:var(--soft); text-align:left; text-transform:uppercase; font-size:9px; } td { border:1px solid var(--line); padding:6px; vertical-align:top; }
        .right { text-align:right; } .status { font-weight:800; text-transform:uppercase; }
        .footer { margin-top:12px; color:var(--muted); font-size:10px; text-align:center; }
        @page { size:A4 landscape; margin:8mm; }
        @media print { body { background:#fff; } .screen-toolbar { display:none; } .page { width:auto; min-height:auto; margin:0; padding:0; box-shadow:none; } }
    </style>
</head>
<body>
    <div class="screen-toolbar">
        <a class="button secondary" href="{{ route('reports.assets.maintenance-history', request()->query()) }}">Kembali</a>
        <button class="button" type="button" onclick="window.print()">Print / Save PDF</button>
    </div>

    <main class="page">
        <section class="header">
            <div class="brand">
                @include('partials.print-brand-mark')
                <div>
                    <h1>{{ $company->legal_name ?: $company->name }}</h1>
                    <p class="muted" style="margin-bottom:4px;">{{ $branch?->name ?? 'Head Office' }}</p>
                    <p class="muted" style="margin-bottom:0;">Periode {{ \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d M Y') }} - {{ \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d M Y') }}</p>
                </div>
            </div>
            <div class="doc-title">
                <h1>ASSET MAINTENANCE HISTORY</h1>
                <p class="muted">Printed {{ now()->format('d M Y H:i') }}</p>
            </div>
        </section>

        <section class="summary">
            <div class="box"><span class="muted">Work Orders</span><strong>{{ number_format($summary['work_order_count']) }}</strong></div>
            <div class="box"><span class="muted">Open</span><strong>{{ number_format($summary['open_count']) }}</strong></div>
            <div class="box"><span class="muted">Overdue</span><strong>{{ number_format($summary['overdue_count']) }}</strong></div>
            <div class="box"><span class="muted">Actual Cost</span><strong>Rp {{ number_format((float) $summary['actual_cost'], 0, ',', '.') }}</strong></div>
        </section>

        <table>
            <thead>
            <tr>
                <th>WO</th>
                <th>Asset</th>
                <th>Type</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Request</th>
                <th>Scheduled</th>
                <th>Vendor</th>
                <th class="right">Est Cost</th>
                <th class="right">Act Cost</th>
                <th class="right">Days</th>
                <th>Control</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td><strong>{{ $row->document_number }}</strong></td>
                    <td><strong>{{ $row->asset_number }}</strong><br>{{ $row->asset_name }}</td>
                    <td>{{ $row->maintenance_type }}</td>
                    <td>{{ $row->priority }}</td>
                    <td>{{ $row->status }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($row->request_date)->format('d M Y') }}</td>
                    <td>{{ $row->scheduled_date ? \Illuminate\Support\Carbon::parse($row->scheduled_date)->format('d M Y') : '-' }}</td>
                    <td>{{ $row->vendor_name ?: '-' }}</td>
                    <td class="right">Rp {{ number_format((float) $row->estimated_cost, 0, ',', '.') }}</td>
                    <td class="right">Rp {{ number_format((float) $row->actual_cost, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $row->days_open, 0, ',', '.') }}</td>
                    <td class="status">{{ $row->control_status }}</td>
                </tr>
            @empty
                <tr><td colspan="12" style="text-align:center;">Tidak ada maintenance history pada filter ini.</td></tr>
            @endforelse
            </tbody>
        </table>

        <p class="footer">SAMS Asset Maintenance History - control report untuk biaya, overdue, dan health aset.</p>
    </main>
</body>
</html>
