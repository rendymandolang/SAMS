<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountingReportService
{
    public function trialBalance(int $companyId, string $from, string $to): array
    {
        $accounts = DB::table('gl_accounts')->where('company_id', $companyId)->where('allow_posting', true)->orderBy('code')->get();
        $opening = $this->movements($companyId, null, date('Y-m-d', strtotime($from.' -1 day')))->groupBy('gl_account_id');
        $period = $this->movements($companyId, $from, $to)->groupBy('gl_account_id');
        $rows = $accounts->map(function (object $account) use ($opening, $period): array {
            $open = $this->net($opening->get($account->id, collect()));
            $moves = $period->get($account->id, collect());
            $debit = (float) $moves->sum('debit');
            $credit = (float) $moves->sum('credit');
            $ending = $open + $debit - $credit;

            return ['account_id' => (int) $account->id, 'code' => $account->code, 'name' => $account->name, 'type' => $account->type, 'opening_debit' => max($open, 0), 'opening_credit' => max(-$open, 0), 'period_debit' => $debit, 'period_credit' => $credit, 'ending_debit' => max($ending, 0), 'ending_credit' => max(-$ending, 0)];
        })->filter(fn (array $r) => array_sum(array_slice($r, 4)) != 0)->values();

        return ['rows' => $rows, 'totals' => $this->totals($rows, ['opening_debit', 'opening_credit', 'period_debit', 'period_credit', 'ending_debit', 'ending_credit'])];
    }

    public function generalLedger(int $companyId, string $from, string $to, ?int $accountId = null): Collection
    {
        $accounts = DB::table('gl_accounts')->where('company_id', $companyId)->where('allow_posting', true)->when($accountId, fn ($q) => $q->where('id', $accountId))->orderBy('code')->get();

        return $accounts->map(function (object $account) use ($companyId, $from, $to) {
            $opening = $this->net($this->movements($companyId, null, date('Y-m-d', strtotime($from.' -1 day')), $account->id));
            $balance = $opening;
            $lines = $this->movements($companyId, $from, $to, $account->id)->map(function ($line) use (&$balance) {
                $balance += (float) $line->debit - (float) $line->credit;
                $line->balance = $balance;

                return $line;
            });

            return ['account' => $account, 'opening' => $opening, 'lines' => $lines, 'closing' => $balance];
        })->filter(fn ($group) => $group['opening'] != 0 || $group['lines']->isNotEmpty())->values();
    }

    public function profitLoss(int $companyId, string $from, string $to): array
    {
        $trial = $this->trialBalance($companyId, $from, $to)['rows'];
        $revenue = $trial->where('type', 'revenue')->map(fn ($r) => $r + ['amount' => $r['period_credit'] - $r['period_debit']]);
        $expense = $trial->where('type', 'expense')->map(fn ($r) => $r + ['amount' => $r['period_debit'] - $r['period_credit']]);
        $totalRevenue = (float) $revenue->sum('amount');
        $totalExpense = (float) $expense->sum('amount');

        return compact('revenue', 'expense', 'totalRevenue', 'totalExpense') + ['netIncome' => $totalRevenue - $totalExpense];
    }

    public function balanceSheet(int $companyId, string $asOf): array
    {
        $trial = $this->trialBalance($companyId, '1900-01-01', $asOf)['rows'];
        $assets = $trial->where('type', 'asset')->map(fn ($r) => $r + ['amount' => $r['ending_debit'] - $r['ending_credit']]);
        $liabilities = $trial->where('type', 'liability')->map(fn ($r) => $r + ['amount' => $r['ending_credit'] - $r['ending_debit']]);
        $equity = $trial->where('type', 'equity')->map(fn ($r) => $r + ['amount' => $r['ending_credit'] - $r['ending_debit']]);
        $income = $this->profitLoss($companyId, '1900-01-01', $asOf)['netIncome'];

        return compact('assets', 'liabilities', 'equity', 'income') + ['totalAssets' => (float) $assets->sum('amount'), 'totalLiabilities' => (float) $liabilities->sum('amount'), 'totalEquity' => (float) $equity->sum('amount') + $income];
    }

    private function movements(int $companyId, ?string $from, ?string $to, ?int $accountId = null): Collection
    {
        return DB::table('journal_entry_lines')->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')->join('gl_accounts', 'gl_accounts.id', '=', 'journal_entry_lines.gl_account_id')->where('journal_entries.company_id', $companyId)->where('journal_entries.status', 'posted')->when($from, fn ($q) => $q->whereDate('journal_entries.journal_date', '>=', $from))->when($to, fn ($q) => $q->whereDate('journal_entries.journal_date', '<=', $to))->when($accountId, fn ($q) => $q->where('gl_accounts.id', $accountId))->select('journal_entry_lines.*', 'journal_entries.document_number', 'journal_entries.journal_date', 'journal_entries.memo', 'gl_accounts.code', 'gl_accounts.name as account_name')->orderBy('journal_entries.journal_date')->orderBy('journal_entries.id')->orderBy('journal_entry_lines.line_number')->get();
    }

    private function net(Collection $lines): float
    {
        return (float) $lines->sum('debit') - (float) $lines->sum('credit');
    }

    private function totals(Collection $rows, array $keys): array
    {
        return collect($keys)->mapWithKeys(fn ($key) => [$key => (float) $rows->sum($key)])->all();
    }
}
