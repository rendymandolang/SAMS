<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->document_number }}</title>
    @vite(['resources/css/app.css'])
    <style>@page{size:A4;margin:14mm}body{background:#fff;color:#172033}.print-sheet{max-width:190mm;margin:auto}.print-head{display:flex;justify-content:space-between;border-bottom:2px solid #172033;padding-bottom:14px}.print-title{text-align:right}.print-title h1{font-size:24px;margin:0}.print-meta{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:18px 0}.print-box{border:1px solid #d9dee8;padding:12px}.print-table{width:100%;border-collapse:collapse}.print-table th,.print-table td{border:1px solid #d9dee8;padding:8px;font-size:11px}.print-table th{background:#f3f5f8}.number{text-align:right}.signatures{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-top:44px;text-align:center}.signature-line{border-top:1px solid #64748b;margin-top:48px;padding-top:6px}@media print{.no-print{display:none}}</style>
</head>
<body>
<main class="print-sheet">
    <header class="print-head"><div><strong>{{ $company->legal_name ?: $company->name }}</strong><div>{{ $company->address }}</div><div>{{ $company->phone }} {{ $company->email }}</div></div><div class="print-title"><h1>SUPPLIER INVOICE</h1><strong>{{ $invoice->document_number }}</strong><div>{{ str($invoice->status)->replace('_', ' ')->upper() }}</div></div></header>
    <section class="print-meta"><div class="print-box"><strong>Supplier</strong><div>{{ $invoice->supplier_name }}</div><div>{{ $invoice->supplier_address }}</div><div>Tax ID: {{ $invoice->supplier_tax_number ?: '-' }}</div></div><div class="print-box"><strong>Invoice Information</strong><div>Supplier Ref: {{ $invoice->supplier_invoice_number }}</div><div>Invoice Date: {{ $invoice->invoice_date }}</div><div>Due Date: {{ $invoice->due_date }}</div><div>Posted Journal: {{ $invoice->journal_number ?: '-' }}</div></div></section>
    <table class="print-table"><thead><tr><th>#</th><th>Account / Department</th><th>Description</th><th>Qty</th><th>Unit Price</th><th>Amount</th></tr></thead><tbody>@foreach($lines as $line)<tr><td>{{ $line->line_number }}</td><td>{{ $line->account_code }} · {{ $line->account_name }}<br>{{ $line->department_name ?: '-' }}</td><td>{{ $line->description }}</td><td class="number">{{ number_format($line->quantity, 4, ',', '.') }}</td><td class="number">{{ number_format($line->unit_price, 2, ',', '.') }}</td><td class="number">{{ number_format($line->amount, 2, ',', '.') }}</td></tr>@endforeach<tr><th colspan="5" class="number">Subtotal</th><th class="number">{{ number_format($invoice->subtotal, 2, ',', '.') }}</th></tr><tr><th colspan="5" class="number">Tax</th><th class="number">{{ number_format($invoice->tax_amount, 2, ',', '.') }}</th></tr><tr><th colspan="5" class="number">TOTAL {{ $invoice->currency }}</th><th class="number">{{ number_format($invoice->total_amount, 2, ',', '.') }}</th></tr><tr><th colspan="5" class="number">Outstanding</th><th class="number">{{ number_format($invoice->outstanding_amount, 2, ',', '.') }}</th></tr></tbody></table>
    @if($invoice->notes)<div class="print-box" style="margin-top:14px"><strong>Notes</strong><div>{{ $invoice->notes }}</div></div>@endif
    <section class="signatures"><div><div class="signature-line">Prepared By</div></div><div><div class="signature-line">Checked By</div></div><div><div class="signature-line">Approved By</div></div></section>
    <footer style="margin-top:30px;font-size:9px;color:#64748b">SuperSoft Enterprise · SaS · {{ now()->format('d M Y H:i') }}</footer>
</main>
<script>window.print()</script>
</body>
</html>
