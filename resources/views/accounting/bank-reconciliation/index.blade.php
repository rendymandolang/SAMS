@extends('layouts.app')
@section('title', 'Bank Reconciliation')
@section('body')
<div class="app-shell">
    @include('partials.sidebar')
    <main class="main">
        <header class="page-header">
            <div><p class="eyebrow">SaS · {{ $company->name }}</p><h1>Bank Reconciliation</h1><p>Import rekening koran, cocokkan transaksi, dan pastikan saldo bank sama dengan General Ledger.</p></div>
        </header>
        @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif

        @if(auth()->user()->hasPermission('accounting.manage'))
        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(320px,1fr));margin-bottom:18px">
            <section class="card">
                <h2>Connect Bank Account</h2>
                <p class="muted">Satu rekening bank terhubung ke satu posting account di General Ledger.</p>
                <form method="POST" action="{{ route('accounting.bank-accounts.store') }}">@csrf
                    <div class="form-grid">
                        <label class="field"><span class="label">Code</span><input class="input" name="code" required maxlength="30"></label>
                        <label class="field"><span class="label">Account Name</span><input class="input" name="name" required></label>
                        <label class="field"><span class="label">Bank</span><input class="input" name="bank_name" required></label>
                        <label class="field"><span class="label">Masked Account No.</span><input class="input" name="account_number_masked" placeholder="**** 1234"></label>
                        <label class="field"><span class="label">Currency</span><input class="input" name="currency" value="{{ $company->currency }}" required maxlength="3"></label>
                        <label class="field"><span class="label">GL Account</span><select class="input" name="gl_account_id" required><option value="">Select account</option>@foreach($assetAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>@endforeach</select></label>
                    </div>
                    <button class="button inline">Connect Account</button>
                </form>
            </section>
            <section class="card">
                <h2>Import Bank Statement</h2>
                <p class="muted">CSV mendukung Date/Tanggal, Description/Keterangan, Amount atau pasangan Debit-Credit, serta Balance/Saldo. <a class="link-action" href="{{ route('accounting.bank-reconciliation.template') }}">Download template</a></p>
                @if($bankAccounts->isEmpty())
                    <div class="error">Hubungkan rekening bank terlebih dahulu.</div>
                @else
                <form method="POST" action="{{ route('accounting.bank-statements.import') }}" enctype="multipart/form-data">@csrf
                    <div class="form-grid">
                        <label class="field full"><span class="label">Bank Account</span><select class="input" name="bank_account_id" required>@foreach($bankAccounts->where('is_active', true) as $account)<option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>@endforeach</select></label>
                        <label class="field full"><span class="label">Statement CSV</span><input class="input" type="file" name="statement" accept=".csv,.txt,text/csv" required></label>
                        <label class="field full"><span class="label">Closing Balance</span><input class="input" type="number" step="0.01" name="closing_balance"><span class="muted">Opsional jika CSV memiliki kolom Balance/Saldo.</span></label>
                    </div>
                    <button class="button primary inline">Import & Auto Match</button>
                </form>
                @endif
            </section>
        </div>
        @endif

        <section class="card" style="margin-bottom:18px">
            <h2>Connected Bank Accounts</h2>
            <div class="table-wrap"><table><thead><tr><th>Code</th><th>Bank Account</th><th>GL Account</th><th>Currency</th><th>Status</th></tr></thead><tbody>
            @forelse($bankAccounts as $account)<tr><td>{{ $account->code }}</td><td>{{ $account->bank_name }}<br><span class="muted">{{ $account->name }} {{ $account->account_number_masked }}</span></td><td>{{ $account->gl_code }} · {{ $account->gl_name }}</td><td>{{ $account->currency }}</td><td><span class="badge">{{ $account->is_active ? 'Active' : 'Inactive' }}</span></td></tr>@empty<tr><td colspan="5">Belum ada rekening bank.</td></tr>@endforelse
            </tbody></table></div>
        </section>

        <section class="card">
            <h2>Statement & Reconciliation History</h2>
            <div class="table-wrap"><table><thead><tr><th>Period</th><th>Account</th><th>File</th><th>Lines</th><th>Closing Balance</th><th>Difference</th><th>Status</th></tr></thead><tbody>
            @forelse($imports as $import)<tr><td><a class="link-action" href="{{ route('accounting.bank-reconciliation.show', $import->reconciliation_id) }}">{{ $import->period_start }} — {{ $import->period_end }}</a></td><td>{{ $import->bank_account_name }}</td><td>{{ $import->original_filename }}</td><td>{{ $import->line_count }}</td><td>Rp {{ number_format($import->closing_balance, 2, ',', '.') }}</td><td>Rp {{ number_format($import->difference, 2, ',', '.') }}</td><td><span class="badge">{{ str($import->reconciliation_status)->title() }}</span></td></tr>@empty<tr><td colspan="7">Belum ada rekening koran yang diimpor.</td></tr>@endforelse
            </tbody></table></div><div style="margin-top:18px">{{ $imports->links() }}</div>
        </section>
    </main>
</div>
@endsection
