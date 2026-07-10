<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print Budget Control</title>
    <style>
        :root { --ink:#111827; --muted:#6b7280; --line:#d1d5db; --soft:#f3f4f6; }
        * { box-sizing: border-box; }
        body { margin:0; color:var(--ink); background:#e5e7eb; font-family:Arial, Helvetica, sans-serif; font-size:11px; }
        .screen-toolbar { display:flex; justify-content:flex-end; gap:10px; max-width:297mm; margin:20px auto 10px; }
        .button { border:0; border-radius:8px; padding:10px 14px; color:#fff; background:#6259ca; cursor:pointer; font-weight:700; text-decoration:none; }
        .button.secondary { color:var(--ink); background:#fff; }
        .page { width:297mm; min-height:210mm; margin:0 auto 20px; padding:14mm; background:#fff; box-shadow:0 18px 60px rgba(15,23,42,.18); }
        .header { display:grid; grid-template-columns:1fr auto; gap:24px; border-bottom:2px solid var(--ink); padding-bottom:14px; }
        .brand { display:flex; gap:12px; align-items:flex-start; }
        .brand-mark { display:grid; width:42px; height:42px; place-items:center; border-radius:10px; color:#fff; background:linear-gradient(145deg,#6259ca,#20c997); font-size:22px; font-weight:900; }
        h1,h2,p { margin-top:0; } h1 { margin-bottom:4px; font-size:21px; letter-spacing:.04em; } h2 { margin-bottom:8px; font-size:13px; }
        .muted { color:var(--muted); } .doc-title { text-align:right; } .doc-title h1 { margin-bottom:8px; font-size:23px; }
        .summary { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin:16px 0; }
        .box { border:1px solid var(--line); border-radius:8px; padding:10px; }
        .box strong { display:block; margin-top:4px; font-size:15px; }
        table { width:100%; border-collapse:collapse; } th { border:1px solid var(--line); padding:7px; background:var(--soft); text-align:left; text-transform:uppercase; font-size:10px; } td { border:1px solid var(--line); padding:7px; vertical-align:top; }
        .right { text-align:right; } .status { font-weight:800; text-transform:uppercase; }
        .footer { margin-top:14px; color:var(--muted); font-size:10px; text-align:center; }
        @page { size:A4 landscape; margin:8mm; }
        @media print { body { background:#fff; } .screen-toolbar { display:none; } .page { width:auto; min-height:auto; margin:0; padding:0; box-shadow:none; } }
    </style>
</head>
<body>
    <div class="screen-toolbar">
        <a class="button secondary" href="{{ route('budget-control.index', request()->query()) }}">Kembali</a>
        <button class="button" type="button" onclick="window.print()">Print / Save PDF</button>
    </div>

    <main class="page">
        <section class="header">
            <div class="brand">
                <div class="brand-mark">S</div>
                <div>
                    <h1>{{ $company->legal_name ?: $company->name }}</h1>
                    <p class="muted" style="margin-bottom:4px;">{{ $branch?->name ?? 'Head Office' }}</p>
                    <p class="muted" style="margin-bottom:0;">{{ $branch?->address ?: 'Alamat perusahaan belum diisi' }}</p>
                </div>
            </div>
            <div class="doc-title">
                <h1>BUDGET CONTROL REPORT</h1>
                <p class="muted">Printed {{ now()->format('d M Y H:i') }}</p>
            </div>
        </section>

        <section class="summary">
            <div class="box"><span class="muted">Allocated</span><strong>Rp {{ number_format((float) $summary['allocated'], 0, ',', '.') }}</strong></div>
            <div class="box"><span class="muted">Committed</span><strong>Rp {{ number_format((float) $summary['committed'], 0, ',', '.') }}</strong></div>
            <div class="box"><span class="muted">Actual</span><strong>Rp {{ number_format((float) $summary['actual'], 0, ',', '.') }}</strong></div>
            <div class="box"><span class="muted">Remaining</span><strong>Rp {{ number_format((float) $summary['remaining'], 0, ',', '.') }}</strong></div>
        </section>

        <table>
            <thead>
            <tr>
                <th>Dept</th>
                <th>Budget</th>
                <th>Account</th>
                <th class="right">Allocated</th>
                <th class="right">Committed</th>
                <th class="right">Actual</th>
                <th class="right">Remaining</th>
                <th class="right">Used %</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td><strong>{{ $line->department_code }}</strong><br>{{ $line->department_name }}</td>
                    <td>{{ $line->budget_name }}</td>
                    <td><strong>{{ $line->account_code }}</strong><br>{{ $line->description }}</td>
                    <td class="right">Rp {{ number_format((float) $line->allocated_amount, 0, ',', '.') }}</td>
                    <td class="right">Rp {{ number_format((float) $line->committed_amount, 0, ',', '.') }}</td>
                    <td class="right">Rp {{ number_format((float) $line->actual_amount, 0, ',', '.') }}</td>
                    <td class="right">Rp {{ number_format((float) $line->remaining_amount, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $line->used_percent, 1, ',', '.') }}%</td>
                    <td class="status">{{ $line->control_status }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align:center;">Tidak ada budget line pada filter ini.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <p class="footer">Budget Control SAMS - committed berasal dari PR submitted, actual berasal dari realisasi/penerimaan yang terkait budget.</p>
    </main>
</body>
</html>
