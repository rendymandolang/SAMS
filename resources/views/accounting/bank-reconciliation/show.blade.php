@extends('layouts.app')
@section('title', 'Bank Reconciliation Detail')
@section('body')
<div class="app-shell">
    @include('partials.sidebar')
    <main class="main">
        <header class="page-header">
            <div><p class="eyebrow">{{ $reconciliation->bank_name }} · {{ $reconciliation->gl_code }}</p><h1>{{ $reconciliation->bank_account_name }}</h1><p>{{ $reconciliation->period_start }} — {{ $reconciliation->period_end }} · {{ $reconciliation->original_filename }}</p></div>
            <div style="display:flex;gap:8px"><a class="button secondary" href="{{ route('accounting.bank-reconciliation.index') }}">Back</a><a class="button secondary" target="_blank" href="{{ route('accounting.bank-reconciliation.print', $reconciliation->id) }}">Print</a></div>
        </header>
        @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif

        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr));margin-bottom:18px">
            <div class="detail-box"><span class="muted">Statement Balance</span><div class="value">Rp {{ number_format($reconciliation->statement_balance, 2, ',', '.') }}</div></div>
            <div class="detail-box"><span class="muted">GL Book Balance</span><div class="value">Rp {{ number_format($reconciliation->book_balance, 2, ',', '.') }}</div></div>
            <div class="detail-box"><span class="muted">Difference</span><div class="value">Rp {{ number_format($reconciliation->difference, 2, ',', '.') }}</div></div>
            <div class="detail-box"><span class="muted">Unresolved</span><div class="value">{{ $unresolved }} / {{ $reconciliation->line_count }}</div></div>
            <div class="detail-box"><span class="muted">Status</span><div class="value">{{ str($reconciliation->status)->title() }}</div></div>
        </div>

        @if($reconciliation->status !== 'completed' && auth()->user()->hasPermission('accounting.post'))
        <section class="card" style="margin-bottom:18px">
            <div class="toolbar section-heading"><div><h2>Completion Control</h2><p class="muted">Semua transaksi harus matched atau excluded, dan difference harus nol.</p></div>
            <form method="POST" action="{{ route('accounting.bank-reconciliation.complete', $reconciliation->id) }}">@csrf<button class="button primary" @disabled($unresolved > 0 || abs((float)$reconciliation->difference) > .005)>Complete & Lock</button></form></div>
        </section>
        @elseif($reconciliation->status === 'completed')
        <div class="notice">Rekonsiliasi selesai dan dikunci pada {{ $reconciliation->completed_at }}.</div>
        @endif

        <section class="card">
            <h2>Statement Transactions</h2>
            <div class="table-wrap"><table><thead><tr><th>Date</th><th>Description</th><th>Amount</th><th>Status</th><th>Journal / Resolution</th></tr></thead><tbody>
            @foreach($lines as $line)
                <tr>
                    <td>{{ $line->transaction_date }}<br><span class="muted">{{ $line->reference ?: '-' }}</span></td>
                    <td>{{ $line->description }}@if($line->running_balance !== null)<br><span class="muted">Balance Rp {{ number_format($line->running_balance, 2, ',', '.') }}</span>@endif</td>
                    <td style="color:{{ $line->amount >= 0 ? '#166534' : '#991b1b' }}">Rp {{ number_format($line->amount, 2, ',', '.') }}</td>
                    <td><span class="badge">{{ str($line->status)->title() }}</span></td>
                    <td>
                        @if($line->status === 'matched')
                            <a class="link-action" href="{{ route('accounting.show', $line->journal_id) }}">{{ $line->journal_number }}</a><br><span class="muted">{{ $line->resolution_note }}</span>
                            @if($reconciliation->status !== 'completed' && auth()->user()->hasPermission('accounting.post'))<form method="POST" action="{{ route('accounting.bank-lines.unmatch', $line->id) }}" style="margin-top:6px">@csrf<button class="button secondary inline">Undo</button></form>@endif
                        @elseif($line->status === 'excluded')
                            <span class="muted">{{ $line->resolution_note }}</span>
                            @if($reconciliation->status !== 'completed' && auth()->user()->hasPermission('accounting.post'))<form method="POST" action="{{ route('accounting.bank-lines.unmatch', $line->id) }}" style="margin-top:6px">@csrf<button class="button secondary inline">Restore</button></form>@endif
                        @elseif($reconciliation->status !== 'completed' && auth()->user()->hasPermission('accounting.post'))
                            <form method="POST" action="{{ route('accounting.bank-lines.match', $line->id) }}">@csrf
                                <select class="input" name="journal_entry_line_id" required><option value="">Match GL transaction</option>@foreach($candidates as $candidate)@if(abs(((float)$candidate->debit-(float)$candidate->credit)-(float)$line->amount) <= .005)<option value="{{ $candidate->id }}">{{ $candidate->journal_date }} · {{ $candidate->document_number }} · Rp {{ number_format((float)$candidate->debit-(float)$candidate->credit, 2, ',', '.') }}</option>@endif @endforeach</select>
                                <button class="button inline" style="margin-top:6px">Match</button>
                            </form>
                            <form method="POST" action="{{ route('accounting.bank-lines.exclude', $line->id) }}" style="margin-top:8px">@csrf<input class="input" name="reason" maxlength="500" placeholder="Audit reason for exclusion" required><button class="button secondary inline" style="margin-top:6px">Exclude</button></form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody></table></div>
        </section>
    </main>
</div>
@endsection
