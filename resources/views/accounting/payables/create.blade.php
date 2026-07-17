@extends('layouts.app', ['title' => 'New Supplier Invoice · '.config('supersoft.product_name')])

@section('body')
<div class="app-shell">
    @include('partials.sidebar')
    <main class="main">
        <header class="page-header"><div><p class="eyebrow">SaS · Accounts Payable</p><h1>New Supplier Invoice</h1><p>Invoice disimpan sebagai draft dan baru memengaruhi ledger setelah posting.</p></div></header>
        @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif

        @if($suppliers->isEmpty() || $debitAccounts->isEmpty() || $liabilityAccounts->isEmpty())
            <section class="card"><h2>Master data belum lengkap</h2><p class="muted">Dibutuhkan supplier aktif, minimal satu expense/asset account, dan satu liability posting account.</p></section>
        @else
        <form method="POST" action="{{ route('accounting.payables.store') }}">@csrf
            <section class="card" style="margin-bottom:18px">
                <h2>Invoice Header</h2>
                <div class="form-grid">
                    <label class="field"><span class="label">Supplier</span><select class="input" name="supplier_id" required><option value="">Select supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->code }} · {{ $supplier->name }}</option>@endforeach</select></label>
                    <label class="field"><span class="label">Supplier Invoice Number</span><input class="input" name="supplier_invoice_number" value="{{ old('supplier_invoice_number') }}" maxlength="100" required></label>
                    <label class="field"><span class="label">Invoice Date</span><input class="input" type="date" name="invoice_date" value="{{ old('invoice_date', today()->toDateString()) }}" required></label>
                    <label class="field"><span class="label">Due Date</span><input class="input" type="date" name="due_date" value="{{ old('due_date', today()->addDays(30)->toDateString()) }}" required></label>
                    <label class="field"><span class="label">Currency</span><input class="input" name="currency" value="{{ old('currency', $company->currency) }}" maxlength="3" required></label>
                    <label class="field"><span class="label">Purchase Order (optional)</span><select class="input" name="purchase_order_id"><option value="">-</option>@foreach($purchaseOrders as $po)<option value="{{ $po->id }}">{{ $po->document_number }} · {{ number_format($po->total_amount, 0, ',', '.') }}</option>@endforeach</select></label>
                    <label class="field"><span class="label">Accounts Payable Account</span><select class="input" name="ap_account_id" required>@foreach($liabilityAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>@endforeach</select></label>
                    <label class="field"><span class="label">Tax Amount</span><input class="input" type="number" name="tax_amount" min="0" step="0.01" value="{{ old('tax_amount', 0) }}"></label>
                    <label class="field"><span class="label">Input Tax Account</span><select class="input" name="tax_account_id"><option value="">Required only when tax applies</option>@foreach($taxAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>@endforeach</select></label>
                    <label class="field full"><span class="label">Notes</span><textarea class="input" name="notes" maxlength="2000">{{ old('notes') }}</textarea></label>
                </div>
            </section>
            <section class="card">
                <h2>Expense / Asset Lines</h2><p class="muted">Subtotal dihitung dari quantity × unit price.</p>
                <div class="table-wrap"><table><thead><tr><th>Account</th><th>Department</th><th>Description</th><th>Qty</th><th>Unit Price</th></tr></thead><tbody>
                    @for($index = 0; $index < 8; $index++)
                        <tr><td><select class="input" name="lines[{{ $index }}][gl_account_id]" @if($index === 0) required @endif><option value="">Select account</option>@foreach($debitAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>@endforeach</select></td><td><select class="input" name="lines[{{ $index }}][department_id]"><option value="">-</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</select></td><td><input class="input" name="lines[{{ $index }}][description]" @if($index === 0) required @endif></td><td><input class="input" type="number" name="lines[{{ $index }}][quantity]" min="0.0001" step="0.0001" value="{{ $index === 0 ? 1 : '' }}"></td><td><input class="input" type="number" name="lines[{{ $index }}][unit_price]" min="0.0001" step="0.01"></td></tr>
                    @endfor
                </tbody></table></div>
                <div style="margin-top:18px;display:flex;gap:8px"><button class="button inline" type="submit">Save Draft</button><a class="button secondary inline" href="{{ route('accounting.payables.index') }}">Cancel</a></div>
            </section>
        </form>
        @endif
    </main>
</div>
@endsection
