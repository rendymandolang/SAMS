<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print Asset {{ $asset->asset_number }}</title>
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
        .asset-card { margin:18px 0; border:2px solid var(--ink); border-radius:14px; padding:18px; }
        .asset-number { font-size:30px; font-weight:900; letter-spacing:.05em; }
        .grid { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; margin-top:16px; }
        .box { border:1px solid var(--line); border-radius:8px; padding:10px; }
        .box strong { display:block; margin-top:4px; font-size:14px; }
        .signatures { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-top:34px; }
        .sign { border:1px solid var(--line); border-radius:8px; min-height:92px; padding:10px; text-align:center; }
        .footer { margin-top:14px; color:var(--muted); font-size:10px; text-align:center; }
        @page { size:A4 portrait; margin:10mm; }
        @media print { body { background:#fff; } .screen-toolbar { display:none; } .page { width:auto; min-height:auto; margin:0; padding:0; box-shadow:none; } }
    </style>
</head>
<body>
    <div class="screen-toolbar">
        <a class="button secondary" href="{{ route('assets.show', $asset->id) }}">Kembali</a>
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
                <h1>ASSET CARD</h1>
                <p class="muted">Printed {{ now()->format('d M Y H:i') }}</p>
            </div>
        </section>

        <section class="asset-card">
            <div class="muted">Asset Number</div>
            <div class="asset-number">{{ $asset->asset_number }}</div>
            <h2 style="margin:12px 0 0;">{{ $asset->asset_name }}</h2>

            <div class="grid">
                <div class="box"><span class="muted">Item</span><strong>{{ $asset->sku }} - {{ $asset->item_name }}</strong></div>
                <div class="box"><span class="muted">Serial Number</span><strong>{{ $asset->serial_number ?: '-' }}</strong></div>
                <div class="box"><span class="muted">Department</span><strong>{{ $asset->department_code ? $asset->department_code.' - '.$asset->department_name : '-' }}</strong></div>
                <div class="box"><span class="muted">Location</span><strong>{{ $asset->location_code ? $asset->location_code.' - '.$asset->location_name : '-' }}</strong></div>
                <div class="box"><span class="muted">Acquisition Date</span><strong>{{ \Illuminate\Support\Carbon::parse($asset->acquisition_date)->format('d M Y') }}</strong></div>
                <div class="box"><span class="muted">Acquisition Cost</span><strong>Rp {{ number_format((float) $asset->acquisition_cost, 0, ',', '.') }}</strong></div>
                <div class="box"><span class="muted">Condition</span><strong>{{ strtoupper($asset->condition) }}</strong></div>
                <div class="box"><span class="muted">Status</span><strong>{{ strtoupper($asset->status) }}</strong></div>
                <div class="box"><span class="muted">Source GR</span><strong>{{ $asset->goods_receipt_number ?: '-' }}</strong></div>
                <div class="box"><span class="muted">Created By</span><strong>{{ $asset->creator_name }}</strong></div>
            </div>
        </section>

        <section>
            <h2>Catatan</h2>
            <p style="line-height:1.7;">{{ $asset->notes ?: '-' }}</p>
        </section>

        <section class="signatures">
            <div class="sign">Dibuat oleh<br><br><br><strong>{{ $asset->creator_name }}</strong></div>
            <div class="sign">Diperiksa oleh<br><br><br><strong>Asset Control</strong></div>
            <div class="sign">Diterima oleh<br><br><br><strong>User / Department</strong></div>
        </section>

        <p class="footer">SAMS Asset Register - kartu aset untuk kontrol fisik, lokasi, kondisi, dan audit.</p>
    </main>
</body>
</html>
