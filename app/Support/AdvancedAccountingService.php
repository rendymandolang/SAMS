<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdvancedAccountingService
{
    public function __construct(private readonly AccountingCurrencyService $currencyService) {}

    /** @param array<string, mixed> $data */
    public function createCreditNote(int $companyId, ?int $branchId, int $userId, array $data): int
    {
        return DB::transaction(function () use ($companyId, $branchId, $userId, $data): int {
            $isPayable = $data['type'] === 'ap';
            $table = $isPayable ? 'ap_invoices' : 'ar_invoices';
            $invoice = DB::table($table)->where('company_id', $companyId)->where('id', $data['invoice_id'])->lockForUpdate()->first();
            if (! $invoice || ! in_array($invoice->status, $isPayable ? ['posted', 'partially_paid'] : ['posted', 'partially_received'], true)) {
                throw ValidationException::withMessages(['invoice_id' => 'Invoice tidak tersedia untuk credit note.']);
            }
            $foreignAmount = round((float) $data['amount'], 4);
            if ($foreignAmount <= 0 || $foreignAmount - (float) $invoice->foreign_outstanding_amount > .005) {
                throw ValidationException::withMessages(['amount' => 'Credit note melebihi outstanding invoice.']);
            }
            $rate = $this->currencyService->rate($companyId, $invoice->currency, $data['credit_date']);
            $carrying = round((float) $invoice->carrying_amount * $foreignAmount / (float) $invoice->foreign_outstanding_amount, 4);
            $amount = $this->currencyService->toBase($foreignAmount, $rate);
            $fx = round($amount - $carrying, 4);
            $settings = DB::table('accounting_settings')->where('company_id', $companyId)->first();
            $requiredFxAccount = $isPayable
                ? ($fx > 0 ? $settings?->realized_fx_loss_account_id : $settings?->realized_fx_gain_account_id)
                : ($fx > 0 ? $settings?->realized_fx_gain_account_id : $settings?->realized_fx_loss_account_id);
            if (abs($fx) > .005 && ! $requiredFxAccount) {
                throw ValidationException::withMessages(['currency' => 'Realized FX gain/loss accounts belum dikonfigurasi.']);
            }
            $controlAccountId = $isPayable ? $invoice->ap_account_id : $invoice->ar_account_id;
            $this->assertAccount($companyId, (int) $controlAccountId, [$isPayable ? 'liability' : 'asset']);
            $this->assertAccount($companyId, (int) $data['offset_account_id'], $isPayable ? ['asset', 'expense'] : ['revenue']);

            return DB::table('accounting_credit_notes')->insertGetId([
                'company_id' => $companyId, 'branch_id' => $branchId, 'type' => $data['type'],
                'ap_invoice_id' => $isPayable ? $invoice->id : null, 'ar_invoice_id' => $isPayable ? null : $invoice->id,
                'document_number' => $this->nextNumber($companyId, $isPayable ? 'ap_credit_note' : 'ar_credit_note', $isPayable ? 'APCN' : 'ARCN', $data['credit_date']),
                'external_reference' => $data['external_reference'] ?? null, 'credit_date' => $data['credit_date'],
                'currency' => $invoice->currency, 'exchange_rate' => $rate, 'foreign_amount' => $foreignAmount,
                'amount' => $amount, 'carrying_amount' => $carrying, 'realized_fx_amount' => $fx, 'control_account_id' => $controlAccountId,
                'offset_account_id' => $data['offset_account_id'], 'status' => 'draft', 'reason' => $data['reason'],
                'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function postCreditNote(int $companyId, int $creditNoteId, int $userId): int
    {
        return DB::transaction(function () use ($companyId, $creditNoteId, $userId): int {
            $note = DB::table('accounting_credit_notes')->where('company_id', $companyId)->where('id', $creditNoteId)->lockForUpdate()->firstOrFail();
            abort_if($note->status !== 'draft', 422, 'Credit note sudah diposting.');
            TransactionPeriodLock::ensureOpen($companyId, 'accounting', $note->credit_date);
            $isPayable = $note->type === 'ap';
            $invoiceTable = $isPayable ? 'ap_invoices' : 'ar_invoices';
            $invoiceId = $isPayable ? $note->ap_invoice_id : $note->ar_invoice_id;
            $invoice = DB::table($invoiceTable)->where('company_id', $companyId)->where('id', $invoiceId)->lockForUpdate()->firstOrFail();
            if ((float) $note->foreign_amount - (float) $invoice->foreign_outstanding_amount > .005 || (float) $note->carrying_amount - (float) $invoice->carrying_amount > .005) {
                throw ValidationException::withMessages(['amount' => 'Outstanding invoice berubah dan tidak lagi mencukupi credit note.']);
            }
            $settings = DB::table('accounting_settings')->where('company_id', $companyId)->first();
            $fx = (float) $note->realized_fx_amount;
            $fxAccountId = $isPayable
                ? ($fx > 0 ? $settings?->realized_fx_loss_account_id : $settings?->realized_fx_gain_account_id)
                : ($fx > 0 ? $settings?->realized_fx_gain_account_id : $settings?->realized_fx_loss_account_id);
            if (abs($fx) > .005 && ! $fxAccountId) {
                throw ValidationException::withMessages(['currency' => 'Realized FX gain/loss accounts belum dikonfigurasi.']);
            }
            $journalTotal = round(max((float) $note->amount, (float) $note->carrying_amount), 4);
            $journalId = DB::table('journal_entries')->insertGetId([
                'company_id' => $companyId, 'branch_id' => $note->branch_id,
                'document_number' => $this->nextNumber($companyId, 'credit_note_journal', 'CNJ', $note->credit_date),
                'journal_date' => $note->credit_date, 'source_type' => $isPayable ? 'ap_credit_note' : 'ar_credit_note',
                'status' => 'posted', 'memo' => 'Credit Note '.$note->document_number.' · '.$note->reason,
                'total_debit' => $journalTotal, 'total_credit' => $journalTotal, 'created_by' => $userId,
                'posted_by' => $userId, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $common = ['journal_entry_id' => $journalId, 'department_id' => null, 'foreign_currency' => $note->currency, 'exchange_rate' => $note->exchange_rate, 'created_at' => now(), 'updated_at' => now()];
            $lines = $isPayable ? [
                $common + ['gl_account_id' => $note->control_account_id, 'description' => 'AP credit note', 'debit' => $note->carrying_amount, 'credit' => 0, 'foreign_debit' => $note->foreign_amount, 'foreign_credit' => 0, 'line_number' => 1],
                $common + ['gl_account_id' => $note->offset_account_id, 'description' => $note->reason, 'debit' => 0, 'credit' => $note->amount, 'foreign_debit' => 0, 'foreign_credit' => $note->foreign_amount, 'line_number' => 2],
            ] : [
                $common + ['gl_account_id' => $note->offset_account_id, 'description' => $note->reason, 'debit' => $note->amount, 'credit' => 0, 'foreign_debit' => $note->foreign_amount, 'foreign_credit' => 0, 'line_number' => 1],
                $common + ['gl_account_id' => $note->control_account_id, 'description' => 'AR credit note', 'debit' => 0, 'credit' => $note->carrying_amount, 'foreign_debit' => 0, 'foreign_credit' => $note->foreign_amount, 'line_number' => 2],
            ];
            if (abs($fx) > .005) {
                $isLoss = $isPayable ? $fx > 0 : $fx < 0;
                $lines[] = ['journal_entry_id' => $journalId, 'gl_account_id' => $fxAccountId, 'department_id' => null, 'description' => 'Realized FX '.($isLoss ? 'loss' : 'gain'), 'debit' => $isLoss ? abs($fx) : 0, 'credit' => $isLoss ? 0 : abs($fx), 'foreign_currency' => null, 'foreign_debit' => 0, 'foreign_credit' => 0, 'exchange_rate' => null, 'line_number' => 3, 'created_at' => now(), 'updated_at' => now()];
            }
            DB::table('journal_entry_lines')->insert($lines);
            $credited = round((float) $invoice->credited_amount + (float) $note->carrying_amount, 4);
            $foreignCredited = round((float) $invoice->foreign_credited_amount + (float) $note->foreign_amount, 4);
            $foreignOutstanding = max(0, round((float) $invoice->foreign_outstanding_amount - (float) $note->foreign_amount, 4));
            $outstanding = max(0, round((float) $invoice->carrying_amount - (float) $note->carrying_amount, 4));
            $settled = $isPayable ? (float) $invoice->paid_amount : (float) $invoice->received_amount;
            DB::table($invoiceTable)->where('id', $invoice->id)->update([
                'credited_amount' => $credited, 'foreign_credited_amount' => $foreignCredited,
                'outstanding_amount' => $outstanding, 'foreign_outstanding_amount' => $foreignOutstanding, 'carrying_amount' => $outstanding,
                'status' => $foreignOutstanding <= .005 ? 'credited' : ($settled > 0 ? ($isPayable ? 'partially_paid' : 'partially_received') : 'posted'),
                'updated_at' => now(),
            ]);
            DB::table('accounting_credit_notes')->where('id', $note->id)->update(['status' => 'posted', 'journal_entry_id' => $journalId, 'posted_by' => $userId, 'posted_at' => now(), 'updated_at' => now()]);

            return $journalId;
        });
    }

    public function reversePayment(int $companyId, int $paymentId, int $userId, string $date, string $reason): int
    {
        return $this->reverseSettlement($companyId, 'ap', $paymentId, $userId, $date, $reason);
    }

    public function reverseReceipt(int $companyId, int $receiptId, int $userId, string $date, string $reason): int
    {
        return $this->reverseSettlement($companyId, 'ar', $receiptId, $userId, $date, $reason);
    }

    public function closeFiscalYear(int $companyId, int $userId, int $year, int $retainedEarningsAccountId): int
    {
        return DB::transaction(function () use ($companyId, $userId, $year, $retainedEarningsAccountId): int {
            $start = sprintf('%04d-01-01', $year);
            $end = sprintf('%04d-12-31', $year);
            $existingClose = DB::table('fiscal_year_closes')->where('company_id', $companyId)->where('fiscal_year', $year)->lockForUpdate()->first();
            abort_if($existingClose?->status === 'completed', 422, 'Fiscal year sudah ditutup.');
            $this->assertAccount($companyId, $retainedEarningsAccountId, ['equity']);
            foreach (range(1, 11) as $month) {
                $monthStart = sprintf('%04d-%02d-01', $year, $month);
                $monthEnd = date('Y-m-t', strtotime($monthStart));
                if (! DB::table('transaction_period_locks')->where('company_id', $companyId)->where('module', 'accounting')->where('starts_on', '<=', $monthStart)->where('ends_on', '>=', $monthEnd)->exists()) {
                    throw ValidationException::withMessages(['fiscal_year' => 'Tutup seluruh bulan Januari sampai November sebelum fiscal-year close.']);
                }
            }
            TransactionPeriodLock::ensureOpen($companyId, 'accounting', $end);
            $balances = DB::table('journal_entry_lines')->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                ->join('gl_accounts', 'gl_accounts.id', '=', 'journal_entry_lines.gl_account_id')
                ->where('journal_entries.company_id', $companyId)->where('journal_entries.status', 'posted')->whereBetween('journal_entries.journal_date', [$start, $end])
                ->whereIn('gl_accounts.type', ['revenue', 'expense'])->groupBy('gl_accounts.id', 'gl_accounts.code', 'gl_accounts.name')
                ->select('gl_accounts.id', 'gl_accounts.code', 'gl_accounts.name', DB::raw('SUM(journal_entry_lines.debit - journal_entry_lines.credit) as net_debit'))->get();
            if ($balances->every(fn (object $balance): bool => abs((float) $balance->net_debit) <= .005)) {
                throw ValidationException::withMessages(['fiscal_year' => 'Tidak ada saldo revenue atau expense yang perlu ditutup.']);
            }
            $lines = [];
            $totalDebit = 0.0;
            $totalCredit = 0.0;
            foreach ($balances as $balance) {
                $net = round((float) $balance->net_debit, 4);
                if (abs($net) <= .005) {
                    continue;
                }
                $debit = $net < 0 ? abs($net) : 0;
                $credit = $net > 0 ? $net : 0;
                $totalDebit += $debit;
                $totalCredit += $credit;
                $lines[] = ['gl_account_id' => $balance->id, 'description' => 'Close '.$balance->code.' · '.$balance->name, 'debit' => $debit, 'credit' => $credit];
            }
            $netIncome = round($totalDebit - $totalCredit, 4);
            $lines[] = ['gl_account_id' => $retainedEarningsAccountId, 'description' => 'Transfer current-year result to retained earnings', 'debit' => $netIncome < 0 ? abs($netIncome) : 0, 'credit' => $netIncome > 0 ? $netIncome : 0];
            $journalId = DB::table('journal_entries')->insertGetId([
                'company_id' => $companyId, 'branch_id' => null, 'document_number' => $this->nextNumber($companyId, 'fiscal_close_journal', 'FCJ', $end),
                'journal_date' => $end, 'source_type' => 'fiscal_close', 'status' => 'posted', 'memo' => 'Fiscal Year Close '.$year,
                'total_debit' => round(max($totalDebit, $totalCredit), 4), 'total_credit' => round(max($totalDebit, $totalCredit), 4),
                'created_by' => $userId, 'posted_by' => $userId, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($lines as $index => $line) {
                DB::table('journal_entry_lines')->insert($line + ['journal_entry_id' => $journalId, 'department_id' => null, 'line_number' => $index + 1, 'created_at' => now(), 'updated_at' => now()]);
            }
            $closeData = [
                'company_id' => $companyId, 'fiscal_year' => $year, 'closing_date' => $end, 'retained_earnings_account_id' => $retainedEarningsAccountId,
                'net_income' => $netIncome, 'status' => 'completed', 'journal_entry_id' => $journalId, 'closed_by' => $userId,
                'reversal_journal_entry_id' => null, 'reopened_by' => null, 'reopened_at' => null,
                'closed_at' => now(), 'updated_at' => now(),
            ];
            if ($existingClose) {
                DB::table('fiscal_year_closes')->where('id', $existingClose->id)->update($closeData);
                $closeId = (int) $existingClose->id;
            } else {
                $closeId = DB::table('fiscal_year_closes')->insertGetId($closeData + ['created_at' => now()]);
            }
            DB::table('transaction_period_locks')->insert([
                'company_id' => $companyId, 'module' => 'accounting', 'starts_on' => $start, 'ends_on' => $end,
                'reason' => 'Fiscal year close #'.$closeId.' · '.$year, 'locked_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $closeId;
        });
    }

    public function reopenFiscalYear(int $companyId, int $closeId, int $userId, string $reason): int
    {
        return DB::transaction(function () use ($companyId, $closeId, $userId, $reason): int {
            $close = DB::table('fiscal_year_closes')->where('company_id', $companyId)->where('id', $closeId)->lockForUpdate()->firstOrFail();
            abort_if($close->status !== 'completed', 422, 'Fiscal year tidak dalam status completed.');
            $reversalId = $this->reverseJournal($companyId, (int) $close->journal_entry_id, $userId, $close->closing_date, 'Fiscal year reopen · '.$reason, 'fiscal_close_reversal');
            DB::table('fiscal_year_closes')->where('id', $close->id)->update(['status' => 'reopened', 'reversal_journal_entry_id' => $reversalId, 'reopened_by' => $userId, 'reopened_at' => now(), 'updated_at' => now()]);
            DB::table('transaction_period_locks')->where('company_id', $companyId)->where('module', 'accounting')->where('reason', 'like', 'Fiscal year close #'.$close->id.' ·%')->delete();

            return $reversalId;
        });
    }

    private function reverseSettlement(int $companyId, string $type, int $settlementId, int $userId, string $date, string $reason): int
    {
        return DB::transaction(function () use ($companyId, $type, $settlementId, $userId, $date, $reason): int {
            $isPayable = $type === 'ap';
            $table = $isPayable ? 'ap_payments' : 'ar_receipts';
            $allocationTable = $isPayable ? 'ap_payment_allocations' : 'ar_receipt_allocations';
            $settlementKey = $isPayable ? 'ap_payment_id' : 'ar_receipt_id';
            $invoiceKey = $isPayable ? 'ap_invoice_id' : 'ar_invoice_id';
            $invoiceTable = $isPayable ? 'ap_invoices' : 'ar_invoices';
            $settlement = DB::table($table)->where('company_id', $companyId)->where('id', $settlementId)->lockForUpdate()->firstOrFail();
            abort_if($settlement->status !== 'posted' || $settlement->reversal_journal_entry_id, 422, 'Settlement sudah direversal atau tidak dapat direversal.');
            TransactionPeriodLock::ensureOpen($companyId, 'accounting', $date);
            $matched = DB::table('journal_entry_lines')->join('bank_statement_lines', 'bank_statement_lines.matched_journal_entry_line_id', '=', 'journal_entry_lines.id')->where('journal_entry_lines.journal_entry_id', $settlement->journal_entry_id)->exists();
            if ($matched) {
                throw ValidationException::withMessages(['reversal' => 'Batalkan bank reconciliation match sebelum settlement direversal.']);
            }
            $reversalId = $this->reverseJournal($companyId, (int) $settlement->journal_entry_id, $userId, $date, $reason, $isPayable ? 'ap_payment_reversal' : 'ar_receipt_reversal');
            $allocations = DB::table($allocationTable)->where($settlementKey, $settlement->id)->get();
            foreach ($allocations as $allocation) {
                $invoice = DB::table($invoiceTable)->where('company_id', $companyId)->where('id', $allocation->{$invoiceKey})->lockForUpdate()->firstOrFail();
                $settledColumn = $isPayable ? 'paid_amount' : 'received_amount';
                $settled = max(0, round((float) $invoice->{$settledColumn} - (float) $allocation->amount, 4));
                $foreignOutstanding = round((float) $invoice->foreign_outstanding_amount + (float) $allocation->foreign_amount, 4);
                $outstanding = round((float) $invoice->carrying_amount + (float) $allocation->amount, 4);
                DB::table($invoiceTable)->where('id', $invoice->id)->update([
                    $settledColumn => $settled, 'outstanding_amount' => $outstanding, 'carrying_amount' => $outstanding,
                    'foreign_outstanding_amount' => $foreignOutstanding,
                    'status' => $foreignOutstanding <= .005 ? ((float) $invoice->foreign_credited_amount > 0 ? 'credited' : ($isPayable ? 'paid' : 'received')) : ($settled > 0 ? ($isPayable ? 'partially_paid' : 'partially_received') : 'posted'),
                    'updated_at' => now(),
                ]);
            }
            DB::table($table)->where('id', $settlement->id)->update(['status' => 'reversed', 'reversal_journal_entry_id' => $reversalId, 'reversed_by' => $userId, 'reversed_at' => now(), 'reversal_reason' => $reason, 'updated_at' => now()]);

            return $reversalId;
        });
    }

    private function reverseJournal(int $companyId, int $journalId, int $userId, string $date, string $memo, string $sourceType): int
    {
        $original = DB::table('journal_entries')->where('company_id', $companyId)->where('id', $journalId)->where('status', 'posted')->lockForUpdate()->firstOrFail();
        abort_if($original->reversed_by_id, 422, 'Jurnal sumber sudah direversal.');
        $lines = DB::table('journal_entry_lines')->where('journal_entry_id', $original->id)->orderBy('line_number')->get();
        $reversalId = DB::table('journal_entries')->insertGetId([
            'company_id' => $companyId, 'branch_id' => $original->branch_id,
            'document_number' => $this->nextNumber($companyId, $sourceType, 'RVJ', $date), 'journal_date' => $date,
            'source_type' => $sourceType, 'reversal_of_id' => $original->id, 'status' => 'posted', 'memo' => $memo,
            'total_debit' => $original->total_credit, 'total_credit' => $original->total_debit,
            'created_by' => $userId, 'posted_by' => $userId, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($lines as $line) {
            DB::table('journal_entry_lines')->insert([
                'journal_entry_id' => $reversalId, 'gl_account_id' => $line->gl_account_id, 'department_id' => $line->department_id,
                'description' => 'Reversal · '.$line->description, 'debit' => $line->credit, 'credit' => $line->debit,
                'foreign_currency' => $line->foreign_currency, 'foreign_debit' => $line->foreign_credit,
                'foreign_credit' => $line->foreign_debit, 'exchange_rate' => $line->exchange_rate,
                'line_number' => $line->line_number, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('journal_entries')->where('id', $original->id)->update(['reversed_by_id' => $reversalId, 'updated_at' => now()]);

        return $reversalId;
    }

    private function nextNumber(int $companyId, string $type, string $prefix, string $date): string
    {
        $period = date('Ym', strtotime($date));
        DB::table('accounting_subledger_sequences')->insertOrIgnore(['company_id' => $companyId, 'document_type' => $type, 'period' => $period, 'next_number' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $sequence = DB::table('accounting_subledger_sequences')->where('company_id', $companyId)->where('document_type', $type)->where('period', $period)->lockForUpdate()->firstOrFail();
        DB::table('accounting_subledger_sequences')->where('id', $sequence->id)->update(['next_number' => (int) $sequence->next_number + 1, 'updated_at' => now()]);

        return $prefix.'-'.$period.'-'.str_pad((string) $sequence->next_number, 5, '0', STR_PAD_LEFT);
    }

    private function assertAccount(int $companyId, int $accountId, array $types): void
    {
        if (! DB::table('gl_accounts')->where('company_id', $companyId)->where('id', $accountId)->whereIn('type', $types)->where('is_active', true)->where('allow_posting', true)->exists()) {
            throw ValidationException::withMessages(['account' => 'GL account tidak valid untuk transaksi ini.']);
        }
    }
}
