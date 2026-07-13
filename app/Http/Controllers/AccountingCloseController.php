<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AccountingCloseController extends Controller
{
    public function index(Request $r, CompanyContext $c): View
    {
        $year = max(2000, min(2100, $r->integer('year', now()->year)));
        $locks = DB::table('transaction_period_locks')->where('company_id', $c->id())->where('module', 'accounting')->whereYear('starts_on', $year)->get();
        $months = collect(range(1, 12))->map(function ($m) use ($year, $locks, $c) {
            $start = sprintf('%04d-%02d-01', $year, $m);
            $end = date('Y-m-t', strtotime($start));
            $lock = $locks->first(fn ($l) => $l->starts_on <= $end && $l->ends_on >= $start);
            $has = DB::table('journal_entries')->where('company_id', $c->id())->where('status', 'posted')->whereBetween('journal_date', [$start, $end])->exists();

            return (object) compact('m', 'start', 'end', 'lock', 'has');
        });

        return view('accounting.close-month', ['company' => $c->current(), 'year' => $year, 'months' => $months]);
    }

    public function store(Request $r, CompanyContext $c): RedirectResponse
    {
        $v = $r->validate(['year' => ['required', 'integer', 'min:2000', 'max:2100'], 'month' => ['required', 'integer', 'min:1', 'max:12']]);
        $start = sprintf('%04d-%02d-01', $v['year'], $v['month']);
        $end = date('Y-m-t', strtotime($start));
        abort_if(DB::table('transaction_period_locks')->where('company_id', $c->id())->where('module', 'accounting')->where('starts_on', '<=', $end)->where('ends_on', '>=', $start)->exists(), 422, 'Month already closed.');
        $id = DB::table('transaction_period_locks')->insertGetId(['company_id' => $c->id(), 'module' => 'accounting', 'starts_on' => $start, 'ends_on' => $end, 'reason' => 'Accounting close month '.date('F Y', strtotime($start)), 'locked_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        AuditLogger::log('accounting_month_closed', 'transaction_period_lock', $id, null, ['period' => $start], $c->id());

        return back()->with('status', 'Accounting month berhasil ditutup.');
    }

    public function destroy(int $lock, CompanyContext $c): RedirectResponse
    {
        $row = DB::table('transaction_period_locks')->where('company_id', $c->id())->where('module', 'accounting')->where('id', $lock)->first();
        abort_unless($row, 404);
        DB::table('transaction_period_locks')->where('id', $row->id)->delete();
        AuditLogger::log('accounting_month_reopened', 'transaction_period_lock', (int) $row->id, (array) $row, null, $c->id());

        return back()->with('status', 'Accounting month dibuka kembali.');
    }
}
