<?php

namespace App\Http\Controllers;

use App\Support\AccountingReportService;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as IlluminateSupportFacadesDB;
use Illuminate\View\View;

class AccountingReportController extends Controller
{
    public function show(Request $r, string $report, CompanyContext $c, AccountingReportService $s): View
    {
        $allowed = ['general-ledger', 'trial-balance', 'profit-loss', 'balance-sheet', 'cash-flow', 'journal-register', 'department-profit-loss'];
        abort_unless(in_array($report, $allowed, true), 404);
        $from = $r->date('from')?->toDateString() ?? now()->startOfMonth()->toDateString();
        $to = $r->date('to')?->toDateString() ?? today()->toDateString();
        abort_if($from > $to, 422, 'Invalid report period.');
        $data = match ($report) {
            'general-ledger' => ['groups' => $s->generalLedger($c->id(), $from, $to, $r->integer('account_id') ?: null)],
            'trial-balance' => ['trial' => $s->trialBalance($c->id(), $from, $to)],
            'profit-loss' => ['statement' => $s->profitLoss($c->id(), $from, $to)],
            'balance-sheet' => ['statement' => $s->balanceSheet($c->id(), $to)],
            'cash-flow' => ['cashFlow' => $s->cashFlow($c->id(), $from, $to)],
            'journal-register' => ['register' => $s->journalRegister($c->id(), $from, $to)],
            'department-profit-loss' => ['statement' => $s->departmentProfitLoss($c->id(), $from, $to, $r->integer('department_id') ?: null)],
        };

        return view('accounting.report', $data + ['company' => $c->current(), 'report' => $report, 'from' => $from, 'to' => $to, 'accounts' => IlluminateSupportFacadesDB::table('gl_accounts')->where('company_id', $c->id())->where('allow_posting', true)->orderBy('code')->get(), 'departments' => IlluminateSupportFacadesDB::table('departments')->where('company_id', $c->id())->where('is_active', true)->orderBy('name')->get(), 'print' => $r->boolean('print')]);
    }
}
