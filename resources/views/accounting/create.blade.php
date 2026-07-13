@extends('layouts.app', ['title' => 'Journal Voucher · '.config('supersoft.product_name')])

@section('body')
<div class="app-shell">
    @include('partials.sidebar')

    <main class="main">
        <header class="page-header">
            <div>
                <p class="eyebrow">SaS · Manual Journal</p>
                <h1>New Journal Voucher</h1>
                <p>Jurnal hanya dapat menggunakan COA milik perusahaan yang sedang aktif.</p>
            </div>
        </header>

        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        @if($accounts->isEmpty())
            <section class="card">
                <h2>Chart of Accounts belum tersedia</h2>
                <p class="muted">Buat minimal dua posting account sebelum membuat Journal Voucher.</p>
                <a class="button inline" href="{{ route('accounting.index') }}">Buka Chart of Accounts</a>
            </section>
        @else
            <form method="POST" action="{{ route('accounting.store') }}">
                @csrf
                <section class="card" style="margin-bottom:18px">
                    <h2>Header Information</h2>
                    <div class="form-grid">
                        <label class="field">
                            <span class="label">Date</span>
                            <input class="input" type="date" name="journal_date" value="{{ old('journal_date', today()->toDateString()) }}" required>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px">
                            <input type="checkbox" name="is_adjustment" value="1" @checked(old('is_adjustment'))>
                            Adjustment
                        </label>
                        <label class="field full">
                            <span class="label">Memo</span>
                            <textarea class="input" name="memo" required>{{ old('memo') }}</textarea>
                        </label>
                    </div>
                </section>

                <section class="card">
                    <h2>Debit & Credit Information</h2>
                    <p class="muted">Isi minimal dua baris. Setiap baris hanya boleh memiliki debit atau credit.</p>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>GL Account</th>
                                    <th>Remark</th>
                                    <th>Debit</th>
                                    <th>Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @for($index = 0; $index < 6; $index++)
                                    <tr>
                                        <td>
                                            <select class="input" name="lines[{{ $index }}][department_id]">
                                                <option value="">-</option>
                                                @foreach($departments as $department)
                                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select class="input" name="lines[{{ $index }}][gl_account_id]" @if($index < 2) required @endif>
                                                <option value="">Pilih akun</option>
                                                @foreach($accounts as $account)
                                                    <option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><input class="input" name="lines[{{ $index }}][description]"></td>
                                        <td><input class="input" type="number" step="0.01" min="0" name="lines[{{ $index }}][debit]" value="0"></td>
                                        <td><input class="input" type="number" step="0.01" min="0" name="lines[{{ $index }}][credit]" value="0"></td>
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top:18px;display:flex;gap:8px">
                        <button class="button inline" type="submit">Save Draft</button>
                        <a class="button secondary inline" href="{{ route('accounting.index') }}">Cancel</a>
                    </div>
                </section>
            </form>
        @endif
    </main>
</div>
@endsection
