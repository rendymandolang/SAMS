<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print {{ $maintenanceRow->document_number }}</title>
    <style>
        :root { --ink:#111827; --muted:#6b7280; --line:#d1d5db; --soft:#f3f4f6; }
        * { box-sizing: border-box; }
        body { margin:0; color:var(--ink); background:#e5e7eb; font-family:Arial, Helvetica, sans-serif; font-size:12px; }
        .screen-toolbar { display:flex; justify-content:flex-end; gap:10px; width:210mm; margin:20px auto 10px; }
        .button { border:0; border-radius:8px; padding:10px 14px; color:#fff; background:#6259ca; cursor:pointer; font-weight:700; text-decoration:none; }
        .button.secondary { color:var(--ink); background:#fff; }
        .page { width:210mm; min-height:297mm; margin:0 auto 20px; padding:16mm; background:#fff; box-shadow:0 18px 60px rgba(15,23,42,.18); }
        .header { display:grid; grid-template-columns:1fr auto; gap:24px; border-bottom:2px solid var(--ink); padding-bottom:14px; }
        .brand { display:flex; gap:12px; align-items:flex-start; }
        .brand-mark { display:grid; width:42px; height:42px; place-items:center; border-radius:10px; color:#fff; background:linear-gradient(145deg,#6259ca,#20c997); font-size:22px; font-weight:900; }
        h1,h2,p { margin-top:0; } h1 { margin-bottom:4px; font-size:22px; letter-spacing:.04em; }
        .muted { color:var(--muted); } .doc-title { text-align:right; } .doc-title h1 { margin-bottom:8px; font-size:22px; }
        .grid { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; margin:18px 0; }
        .box { border:1px solid var(--line); border-radius:8px; padding:10px; }
        .box strong { display:block; margin-top:4px; font-size:14px; }
        .notes { border:1px solid var(--line); border-radius:8px; padding:12px; min-height:90px; line-height:1.7; }
        .signatures { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-top:34px; }
        .sign { border:1px solid var(--line); border-radius:8px; min-height:92px; padding:10px; text-align:center; }
        @page { size:A4 portrait; margin:10mm; }
        @media print { body { background:#fff; } .screen-toolbar { display:none; } .page { width:auto; min-height:auto; margin:0; padding:0; box-shadow:none; } }
    </style>
</head>
<body>
    <div class="screen-toolbar">
        <a class="button secondary" href="{{ route('asset-maintenances.show', $maintenanceRow->id) }}">Kembali</a>
        <button class="button" type="button" onclick="window.print()">Print / Save PDF</button>
    </div>

    <main class="page">
        <section class="header">
            <div class="brand">
                @include('partials.print-brand-mark')
                <div>
                    <h1>{{ $company->legal_name ?: $company->name }}</h1>
                    <p class="muted">{{ $branch?->name ?? 'Head Office' }}</p>
                </div>
            </div>
            <div class="doc-title">
                <h1>MAINTENANCE WORK ORDER</h1>
                <p class="muted">{{ $maintenanceRow->document_number }}</p>
            </div>
        </section>

        <section class="grid">
            <div class="box"><span class="muted">Asset</span><strong>{{ $maintenanceRow->asset_number }} - {{ $maintenanceRow->asset_name }}</strong></div>
            <div class="box"><span class="muted">Status</span><strong>{{ strtoupper($maintenanceRow->status) }}</strong></div>
            <div class="box"><span class="muted">Type / Priority</span><strong>{{ strtoupper($maintenanceRow->maintenance_type) }} / {{ strtoupper($maintenanceRow->priority) }}</strong></div>
            <div class="box"><span class="muted">Request Date</span><strong>{{ \Illuminate\Support\Carbon::parse($maintenanceRow->request_date)->format('d M Y') }}</strong></div>
            <div class="box"><span class="muted">Vendor</span><strong>{{ $maintenanceRow->vendor_name ?: '-' }}</strong></div>
            <div class="box"><span class="muted">Cost</span><strong>Est Rp {{ number_format((float) $maintenanceRow->estimated_cost, 0, ',', '.') }} / Act Rp {{ number_format((float) $maintenanceRow->actual_cost, 0, ',', '.') }}</strong></div>
        </section>

        <h2>Issue Description</h2>
        <div class="notes">{{ $maintenanceRow->issue_description }}</div>

        <h2 style="margin-top:18px;">Resolution Notes</h2>
        <div class="notes">{{ $maintenanceRow->resolution_notes ?: '-' }}</div>

        <section class="signatures">
            <div class="sign">Requested By<br><br><br><strong>{{ $maintenanceRow->requester_name }}</strong></div>
            <div class="sign">Technician / Vendor<br><br><br><strong>{{ $maintenanceRow->vendor_name ?: '-' }}</strong></div>
            <div class="sign">Approved / Verified<br><br><br><strong>Asset Control</strong></div>
        </section>
    </main>
</body>
</html>
