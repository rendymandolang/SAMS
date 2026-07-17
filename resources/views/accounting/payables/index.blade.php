@extends('layouts.app', ['title' => 'Accounts Payable · '.config('supersoft.product_name')])

@section('body')
<div class="app-shell">
    @include('partials.sidebar')
    <main class="main">
        <header class="page-header">
            <div><p class="eyebrow">SaS · {{ $company->name }}</p><h1>Accounts Payable</h1><p>Supplier invoice, outstanding balance, partial payment, dan AP aging.</p></div>
            @if(auth()->user()->hasPermission('accounting.manage'))<a class="button primary" href="{{ route('accounting.payables.create') }}">New Supplier Invoice</a>@endif
        </header>
        @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif

        <section class="grid stats" style="margin-bottom:18px">
            <div class="card metric-card"><div class="muted">Total Outstanding</div><div class="stat-value" style="font-size:22px">Rp {{ number_format($totalOutstanding, 0, ',', '.') }}</div></div>
            <div class="card metric-card"><div class="muted">Current</div><div class="stat-value" style="font-size:22px">Rp {{ number_format($aging['current'], 0, ',', '.') }}</div></div>
            <div class="card metric-card"><div class="muted">1–30 Days</div><div class="stat-value" style="font-size:22px">Rp {{ number_format($aging['days_1_30'], 0, ',', '.') }}</div></div>
            <div class="card metric-card"><div class="muted">Over 30 Days</div><div class="stat-value" style="font-size:22px">Rp {{ number_format($aging['days_31_60'] + $aging['days_61_90'] + $aging['days_over_90'], 0, ',', '.') }}</div></div>
        </section>

        <section class="card" style="margin-bottom:18px">
            <h2>Aging Detail</h2>
            <div class="detail-grid">
                @foreach(['current' => 'Current', 'days_1_30' => '1–30 Days', 'days_31_60' => '31–60 Days', 'days_61_90' => '61–90 Days', 'days_over_90' => 'Over 90 Days'] as $key => $label)
                    <div class="detail-box"><span class="muted">{{ $label }}</span><div class="value">Rp {{ number_format($aging[$key], 0, ',', '.') }}</div></div>
                @endforeach
            </div>
        </section>

        <section class="card">
            <div class="toolbar section-heading">
                <h2>Supplier Invoice Register</h2>
                <form method="GET"><select class="input" name="status" onchange="this.form.submit()"><option value="">All Status</option>@foreach(['draft','posted','partially_paid','paid'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</select></form>
            </div>
            <div class="table-wrap"><table><thead><tr><th>Document</th><th>Supplier Invoice</th><th>Supplier</th><th>Invoice / Due</th><th>Total</th><th>Outstanding</th><th>Status</th></tr></thead><tbody>
                @forelse($invoices as $invoice)
                    <tr><td><a class="link-action" href="{{ route('accounting.payables.show', $invoice->id) }}">{{ $invoice->document_number }}</a></td><td>{{ $invoice->supplier_invoice_number }}</td><td>{{ $invoice->supplier_name }}</td><td>{{ $invoice->invoice_date }}<br><span class="muted">Due {{ $invoice->due_date }}</span></td><td>Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</td><td>Rp {{ number_format($invoice->outstanding_amount, 0, ',', '.') }}</td><td><span class="badge">{{ str($invoice->status)->replace('_', ' ')->title() }}</span></td></tr>
                @empty<tr><td colspan="7">Belum ada supplier invoice.</td></tr>@endforelse
            </tbody></table></div>
            <div style="margin-top:18px">{{ $invoices->links() }}</div>
        </section>
    </main>
</div>
@endsection
