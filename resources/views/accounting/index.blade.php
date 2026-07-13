@extends('layouts.app', ['title' => 'SaS Accounting · '.config('supersoft.product_name')])

@section('body')
<div class="app-shell">
    @include('partials.sidebar')

    <main class="main">
        <header class="page-header">
            <div>
                <p class="eyebrow">SaS · {{ $company->name }}</p>
                <h1>General Accounting</h1>
                <p>Chart of Accounts perusahaan, Journal Voucher, posting, dan General Ledger.</p>
            </div>
            @if(auth()->user()->hasPermission('accounting.manage') && $accounts->where('allow_posting', true)->isNotEmpty())
                <a class="button primary" href="{{ route('accounting.create') }}">Buat Journal Voucher</a>
            @endif
        </header>

        @if(session('status'))
            <div class="notice">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        @if(auth()->user()->hasPermission('accounting.manage'))
            <section class="card" style="margin-bottom:18px">
                <h2>Tambah Chart of Account</h2>
                <p class="muted">SaS tidak membuat template COA otomatis. Buat akun sesuai struktur dan kebutuhan perusahaan Anda.</p>

                <form method="POST" action="{{ route('accounting.accounts.store') }}">
                    @csrf
                    <div class="form-grid">
                        <label class="field">
                            <span class="label">Account Code</span>
                            <input class="input" name="code" value="{{ old('code') }}" maxlength="40" placeholder="Contoh: 1100" required>
                        </label>
                        <label class="field">
                            <span class="label">Account Name</span>
                            <input class="input" name="name" value="{{ old('name') }}" maxlength="255" placeholder="Contoh: Cash and Bank" required>
                        </label>
                        <label class="field">
                            <span class="label">Account Type</span>
                            <select class="input" name="type" required>
                                @foreach(['asset' => 'Asset', 'liability' => 'Liability', 'equity' => 'Equity', 'revenue' => 'Revenue', 'expense' => 'Expense'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field">
                            <span class="label">Normal Balance</span>
                            <select class="input" name="normal_balance" required>
                                <option value="debit" @selected(old('normal_balance') === 'debit')>Debit</option>
                                <option value="credit" @selected(old('normal_balance') === 'credit')>Credit</option>
                            </select>
                        </label>
                    </div>

                    <div style="display:flex;gap:20px;flex-wrap:wrap;margin:4px 0 18px">
                        <label style="display:flex;align-items:center;gap:8px">
                            <input type="checkbox" name="allow_posting" value="1" @checked(old('allow_posting', true))>
                            Dapat digunakan untuk posting
                        </label>
                        <label style="display:flex;align-items:center;gap:8px">
                            <input type="checkbox" name="confirm_similar" value="1" @checked(old('confirm_similar'))>
                            Saya sudah meninjau kemungkinan akun serupa
                        </label>
                    </div>

                    <button class="button inline" type="submit">Tambah Account</button>
                </form>
            </section>
        @endif

        <section class="card" style="margin-bottom:18px">
            <h2>Chart of Accounts</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Account</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Normal</th>
                            <th>Posting</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($accounts as $account)
                            <tr>
                                <td><strong>{{ $account->code }}</strong></td>
                                <td>{{ $account->name }}</td>
                                <td>{{ str($account->type)->title() }}</td>
                                <td>{{ strtoupper($account->normal_balance) }}</td>
                                <td>{{ $account->allow_posting ? 'Yes' : 'Header' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">COA masih kosong. Tambahkan akun pertama sesuai kebutuhan perusahaan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h2>Journal Register</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Ref Number</th>
                            <th>Date</th>
                            <th>Memo</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $entry)
                            <tr>
                                <td><a class="link-action" href="{{ route('accounting.show', $entry->id) }}">{{ $entry->document_number }}</a></td>
                                <td>{{ $entry->journal_date }}</td>
                                <td>{{ $entry->memo }}</td>
                                <td>Rp {{ number_format($entry->total_debit, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format($entry->total_credit, 0, ',', '.') }}</td>
                                <td><span class="status">{{ $entry->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6">Belum ada Journal Voucher.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
@endsection
