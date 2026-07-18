<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->document_number }}</title>
    @vite(['resources/css/app.css'])
    <style>@page{size:A4;margin:14mm}body{background:#fff}.sheet{max-width:190mm;margin:auto}.head{display:flex;justify-content:space-between;border-bottom:2px solid #172033;padding-bottom:14px}table{width:100%;border-collapse:collapse;margin-top:18px}th,td{border:1px solid #d9dee8;padding:8px;font-size:11px}.right{text-align:right}.sign{display:grid;grid-template-columns:repeat(3,1fr);gap:25px;text-align:center;margin-top:60px}.line{border-top:1px solid #64748b;padding-top:6px}</style>
</head>
<body>
<main class="sheet">
    <header class="head"><div><strong>{{ $company->legal_name ?: $company->name }}</strong><div>{{ $company->address }}</div></div><div style="text-align:right"><h1>CUSTOMER INVOICE</h1><strong>{{ $invoice->document_number }}</strong></div></header>
    <div style="display:flex;justify-content:space-between;margin-top:18px"><div><strong>Bill To</strong><div>{{ $invoice->customer_name }}</div><div>{{ $invoice->customer_address }}</div><div>Tax ID: {{ $invoice->customer_tax_number ?: '-' }}</div></div><div>Invoice: {{ $invoice->invoice_date }}<br>Due: {{ $invoice->due_date }}<br>Reference: {{ $invoice->customer_reference ?: '-' }}</div></div>
    <table>
        <thead><tr><th>#</th><th>Description</th><th>Account</th><th>Qty</th><th>Unit Price</th><th>Amount</th></tr></thead>
        <tbody>
        @foreach($lines as $line)
            <tr><td>{{ $line->line_number }}</td><td>{{ $line->description }}</td><td>{{ $line->account_code }} · {{ $line->account_name }}</td><td class="right">{{ $line->quantity }}</td><td class="right">{{ number_format($line->foreign_unit_price, 2, ',', '.') }}</td><td class="right">{{ number_format($line->foreign_amount, 2, ',', '.') }}</td></tr>
        @endforeach
        <tr><th colspan="5" class="right">Subtotal</th><th class="right">{{ number_format($invoice->foreign_subtotal, 2, ',', '.') }}</th></tr>
        <tr><th colspan="5" class="right">Tax</th><th class="right">{{ number_format($invoice->foreign_tax_amount, 2, ',', '.') }}</th></tr>
        <tr><th colspan="5" class="right">TOTAL {{ $invoice->currency }}</th><th class="right">{{ number_format($invoice->foreign_total_amount, 2, ',', '.') }}</th></tr>
        <tr><th colspan="5" class="right">Base Amount</th><th class="right">Rp {{ number_format($invoice->total_amount, 2, ',', '.') }}</th></tr>
        </tbody>
    </table>
    <div class="sign"><div class="line">Prepared By</div><div class="line">Checked By</div><div class="line">Approved By</div></div>
    <footer style="margin-top:30px;font-size:9px">SuperSoft Enterprise · SaS · {{ now()->format('d M Y H:i') }}</footer>
</main>
<script>window.print()</script>
</body>
</html>
