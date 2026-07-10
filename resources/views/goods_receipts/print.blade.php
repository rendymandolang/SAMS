<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print {{ $header->document_number }}</title>
    <style>
        :root { --ink:#111827; --muted:#6b7280; --line:#d1d5db; --soft:#f3f4f6; }
        * { box-sizing: border-box; }
        body { margin:0; color:var(--ink); background:#e5e7eb; font-family:Arial, Helvetica, sans-serif; font-size:12px; }
        .screen-toolbar { display:flex; justify-content:flex-end; gap:10px; max-width:210mm; margin:20px auto 10px; }
        .button { border:0; border-radius:8px; padding:10px 14px; color:#fff; background:#6259ca; cursor:pointer; font-weight:700; text-decoration:none; }
        .button.secondary { color:var(--ink); background:#fff; }
        .page { width:210mm; min-height:297mm; margin:0 auto 20px; padding:16mm; background:#fff; box-shadow:0 18px 60px rgba(15,23,42,.18); }
        .header { display:grid; grid-template-columns:1fr auto; gap:24px; border-bottom:2px solid var(--ink); padding-bottom:14px; }
        .brand { display:flex; gap:12px; align-items:flex-start; }
        .brand-mark { display:grid; width:46px; height:46px; place-items:center; border-radius:10px; color:#fff; background:linear-gradient(145deg,#6259ca,#20c997); font-size:24px; font-weight:900; }
        h1,h2,p { margin-top:0; } h1 { margin-bottom:4px; font-size:22px; letter-spacing:.04em; } h2 { margin-bottom:8px; font-size:14px; }
        .muted { color:var(--muted); } .doc-title { text-align:right; } .doc-title h1 { margin-bottom:8px; font-size:24px; }
        .status { display:inline-block; border:1px solid var(--line); border-radius:999px; padding:5px 9px; font-weight:800; text-transform:uppercase; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-top:18px; }
        .box { border:1px solid var(--line); border-radius:10px; padding:12px; }
        .info-table { width:100%; border-collapse:collapse; } .info-table td { padding:3px 0; vertical-align:top; } .info-table td:first-child { width:120px; color:var(--muted); }
        table.items { width:100%; border-collapse:collapse; margin-top:18px; } .items th { border:1px solid var(--line); padding:8px; background:var(--soft); text-align:left; font-size:11px; text-transform:uppercase; } .items td { border:1px solid var(--line); padding:8px; vertical-align:top; }
        .right { text-align:right; } .center { text-align:center; }
        .notes { margin-top:18px; min-height:54px; border:1px solid var(--line); border-radius:10px; padding:12px; }
        .signatures { display:grid; grid-template-columns:repeat(3,1fr); gap:18px; margin-top:28px; }
        .signature-box { min-height:112px; border:1px solid var(--line); border-radius:10px; padding:10px; text-align:center; }
        .signature-line { margin-top:58px; border-top:1px solid var(--ink); padding-top:6px; font-weight:700; }
        .footer { margin-top:18px; color:var(--muted); font-size:11px; text-align:center; }
        @page { size:A4; margin:10mm; }
        @media print { body { background:#fff; } .screen-toolbar { display:none; } .page { width:auto; min-height:auto; margin:0; padding:0; box-shadow:none; } }
    </style>
</head>
<body>
    <div class="screen-toolbar">
        <a class="button secondary" href="{{ route('goods-receipts.show', $header->id) }}">Kembali</a>
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
                <h1>GOODS RECEIPT</h1>
                <div class="status">{{ $header->status }}</div>
            </div>
        </section>

        <section class="grid">
            <div class="box">
                <h2>Penerimaan</h2>
                <table class="info-table">
                    <tr><td>Gudang</td><td><strong>{{ $header->storage_location_code }} · {{ $header->storage_location_name }}</strong></td></tr>
                    <tr><td>Penerima</td><td>{{ $header->receiver_name }}</td></tr>
                    <tr><td>Tanggal Terima</td><td>{{ \Illuminate\Support\Carbon::parse($header->received_at)->format('d M Y H:i') }}</td></tr>
                    <tr><td>Posted</td><td>{{ $header->posted_at ? \Illuminate\Support\Carbon::parse($header->posted_at)->format('d M Y H:i') : '-' }}</td></tr>
                </table>
            </div>

            <div class="box">
                <h2>Informasi Dokumen</h2>
                <table class="info-table">
                    <tr><td>No GR</td><td><strong>{{ $header->document_number }}</strong></td></tr>
                    <tr><td>No PO</td><td>{{ $header->purchase_order_number }}</td></tr>
                    <tr><td>Surat Jalan</td><td>{{ $header->supplier_delivery_number ?: '-' }}</td></tr>
                </table>
            </div>
        </section>

        <table class="items">
            <thead>
            <tr>
                <th class="center" style="width:32px;">No</th>
                <th>SKU</th>
                <th>Item</th>
                <th class="right">Accepted</th>
                <th class="right">Rejected</th>
                <th>Satuan</th>
                <th>Lot</th>
                <th>Expiry</th>
                <th>Alasan Reject</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($items as $index => $item)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $item->sku }}</td>
                    <td>{{ $item->item_name }}</td>
                    <td class="right">{{ number_format((float) $item->accepted_quantity, 2, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $item->rejected_quantity, 2, ',', '.') }}</td>
                    <td>{{ $item->unit_code }}</td>
                    <td>{{ $item->lot_number ?: '-' }}</td>
                    <td>{{ $item->expiry_date ?: '-' }}</td>
                    <td>{{ $item->rejection_reason ?: '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <section class="notes">
            <strong>Catatan</strong>
            <p style="margin:8px 0 0;line-height:1.6;">{{ $header->notes ?: '-' }}</p>
        </section>

        <section class="signatures">
            <div class="signature-box"><strong>Diterima oleh</strong><div class="signature-line">{{ $header->receiver_name }}</div></div>
            <div class="signature-box"><strong>Diperiksa oleh</strong><div class="signature-line">Warehouse Lead</div></div>
            <div class="signature-box"><strong>Diketahui oleh</strong><div class="signature-line">Purchasing / Finance</div></div>
        </section>

        <p class="footer">Dicetak dari SAMS pada {{ now()->format('d M Y H:i') }} · Goods Receipt posted akan menambah saldo stock movement.</p>
    </main>
</body>
</html>
