@extends('layouts.app', ['title' => $entry->document_number.' · '.config('supersoft.product_name')])

@section('body')
<div class="app-shell">
    @include('partials.sidebar')

    <main class="main">
        <header class="page-header">
            <div>
                <p class="eyebrow">Journal Voucher</p>
                <h1>{{ $entry->document_number }}</h1>
                <p>{{ $entry->journal_date }} · {{ $entry->memo }}</p>
            </div>
            <div style="display:flex;gap:8px">
                <a class="button secondary" href="{{ route('accounting.print', $entry->id) }}" target="_blank">Print</a>
                @if($entry->status === 'draft' && auth()->user()->hasPermission('accounting.post'))
                    <form method="POST" action="{{ route('accounting.post', $entry->id) }}">
                        @csrf
                        <button class="button primary">Post Journal</button>
                    </form>
                @endif
            </div>
        </header>

        @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif

        @if($entry->reversal_of_id)
            <div class="notice">Jurnal ini adalah reversal dari <a href="{{ route('accounting.show', $entry->reversal_of_id) }}">jurnal asli</a>.</div>
        @elseif($entry->reversed_by_id)
            <div class="error" style="color:#92400e;background:#fef3c7">Jurnal ini telah direversal oleh <a href="{{ route('accounting.show', $entry->reversed_by_id) }}">jurnal reversal</a>.</div>
        @endif

        <section class="card">
            <div class="detail-grid">
                <div class="detail-box"><span class="muted">Status</span><div class="value">{{ $entry->reversed_by_id ? 'Reversed' : str($entry->status)->title() }}</div></div>
                <div class="detail-box"><span class="muted">Adjustment</span><div class="value">{{ $entry->is_adjustment ? 'Yes' : 'No' }}</div></div>
                <div class="detail-box"><span class="muted">Debit</span><div class="value">Rp {{ number_format($entry->total_debit, 0, ',', '.') }}</div></div>
                <div class="detail-box"><span class="muted">Credit</span><div class="value">Rp {{ number_format($entry->total_credit, 0, ',', '.') }}</div></div>
            </div>

            @if($entry->reversal_reason)
                <div class="detail-box" style="margin-top:18px"><span class="muted">Reversal Reason</span><div class="value">{{ $entry->reversal_reason }}</div></div>
            @endif

            <div class="table-wrap" style="margin-top:18px">
                <table>
                    <thead><tr><th>Sub Department</th><th>Account</th><th>Remark</th><th>Debit</th><th>Credit</th></tr></thead>
                    <tbody>
                        @foreach($lines as $line)
                            <tr>
                                <td>{{ $line->department_name ?: '-' }}</td>
                                <td><strong>{{ $line->code }}</strong> · {{ $line->account_name }}</td>
                                <td>{{ $line->description }}</td>
                                <td>Rp {{ number_format($line->debit, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format($line->credit, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @if($entry->status === 'posted' && ! $entry->reversal_of_id && ! $entry->reversed_by_id && auth()->user()->hasPermission('accounting.post'))
            <section class="card" style="margin-top:18px">
                <h2>Controlled Reversal</h2>
                <p class="muted">Jurnal posted tidak diubah atau dihapus. SaS membuat jurnal lawan dan mempertahankan keduanya dalam audit trail.</p>
                <form method="POST" action="{{ route('accounting.reverse', $entry->id) }}">
                    @csrf
                    <div class="form-grid">
                        <label class="field">
                            <span class="label">Reversal Date</span>
                            <input class="input" type="date" name="reversal_date" value="{{ old('reversal_date', today()->toDateString()) }}" min="{{ $entry->journal_date }}" required>
                        </label>
                        <label class="field full">
                            <span class="label">Reason</span>
                            <textarea class="input" name="reversal_reason" rows="3" maxlength="2000" required>{{ old('reversal_reason') }}</textarea>
                        </label>
                    </div>
                    <button class="button inline" type="submit">Create & Post Reversal</button>
                </form>
            </section>
        @endif
    </main>
</div>
@endsection
