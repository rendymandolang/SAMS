<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountsReceivableService
{
    public function __construct(private readonly AccountingCurrencyService $currencyService) {}

    /** @param array<string, mixed> $data */
    public function createInvoice(int $companyId, ?int $branchId, int $userId, array $data): int
    {
        return DB::transaction(function () use ($companyId, $branchId, $userId, $data): int {
            $rate = $this->currencyService->rate($companyId, $data['currency'], $data['invoice_date']);
            $foreignSubtotal = round(collect($data['lines'])->sum(fn (array $line): float => (float) $line['quantity'] * (float) $line['unit_price']), 4);
            $taxCode = empty($data['tax_code_id']) ? null : DB::table('accounting_tax_codes')->where('company_id', $companyId)->where('id', $data['tax_code_id'])->where('type', 'sales')->where('is_active', true)->firstOrFail();
            $foreignTax = $taxCode ? round($foreignSubtotal * (float) $taxCode->rate_percent / 100, 4) : round((float) ($data['tax_amount'] ?? 0), 4);
            $foreignTotal = round($foreignSubtotal + $foreignTax, 4);
            $subtotal = $this->currencyService->toBase($foreignSubtotal, $rate);
            $tax = $this->currencyService->toBase($foreignTax, $rate);
            $total = $this->currencyService->toBase($foreignTotal, $rate);
            $invoiceId = DB::table('ar_invoices')->insertGetId([
                'company_id' => $companyId, 'branch_id' => $branchId, 'customer_id' => $data['customer_id'],
                'document_number' => $this->nextNumber($companyId, 'customer_invoice', 'CI', $data['invoice_date']),
                'customer_reference' => $data['customer_reference'] ?? null, 'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'], 'currency' => $data['currency'], 'exchange_rate' => $rate,
                'foreign_subtotal' => $foreignSubtotal, 'foreign_tax_amount' => $foreignTax, 'foreign_total_amount' => $foreignTotal,
                'foreign_outstanding_amount' => $foreignTotal, 'carrying_amount' => $total, 'status' => 'draft',
                'subtotal' => $subtotal, 'tax_amount' => $tax, 'total_amount' => $total, 'received_amount' => 0,
                'outstanding_amount' => $total, 'ar_account_id' => $data['ar_account_id'],
                'tax_account_id' => $taxCode?->gl_account_id ?? ($data['tax_account_id'] ?? null), 'tax_code_id' => $taxCode?->id, 'notes' => $data['notes'] ?? null,
                'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($data['lines'] as $index => $line) {
                $foreignAmount = round((float) $line['quantity'] * (float) $line['unit_price'], 4);
                $baseAmount = $this->currencyService->toBase($foreignAmount, $rate);
                DB::table('ar_invoice_lines')->insert([
                    'ar_invoice_id' => $invoiceId, 'gl_account_id' => $line['gl_account_id'],
                    'department_id' => $line['department_id'] ?? null, 'description' => trim($line['description']),
                    'quantity' => $line['quantity'], 'unit_price' => $baseAmount / (float) $line['quantity'],
                    'foreign_unit_price' => $line['unit_price'], 'foreign_amount' => $foreignAmount, 'amount' => $baseAmount,
                    'line_number' => $index + 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $invoiceId;
        });
    }

    public function postInvoice(int $companyId, int $invoiceId, int $userId): int
    {
        return DB::transaction(function () use ($companyId, $invoiceId, $userId): int {
            $invoice = DB::table('ar_invoices')->join('accounting_customers', 'accounting_customers.id', '=', 'ar_invoices.customer_id')
                ->where('ar_invoices.company_id', $companyId)->where('ar_invoices.id', $invoiceId)
                ->select('ar_invoices.*', 'accounting_customers.name as customer_name')->lockForUpdate()->first();
            abort_unless($invoice, 404);
            abort_if($invoice->status !== 'draft', 422, 'Customer invoice sudah diposting.');
            TransactionPeriodLock::ensureOpen($companyId, 'accounting', $invoice->invoice_date);
            $lines = DB::table('ar_invoice_lines')->where('ar_invoice_id', $invoice->id)->orderBy('line_number')->get();
            abort_if($lines->isEmpty(), 422, 'Customer invoice tidak memiliki detail.');
            $this->assertAccount($companyId, (int) $invoice->ar_account_id, ['asset']);
            if ((float) $invoice->tax_amount > 0) {
                $this->assertAccount($companyId, (int) $invoice->tax_account_id, ['liability']);
            }
            foreach ($lines->pluck('gl_account_id')->unique() as $accountId) {
                $this->assertAccount($companyId, (int) $accountId, ['revenue']);
            }

            $journalId = DB::table('journal_entries')->insertGetId([
                'company_id' => $companyId, 'branch_id' => $invoice->branch_id,
                'document_number' => $this->nextNumber($companyId, 'ar_invoice_journal', 'ARJ', $invoice->invoice_date),
                'journal_date' => $invoice->invoice_date, 'source_type' => 'ar_invoice', 'status' => 'posted',
                'memo' => 'Customer Invoice '.$invoice->document_number.' · '.$invoice->customer_name,
                'total_debit' => $invoice->total_amount, 'total_credit' => $invoice->total_amount,
                'created_by' => $userId, 'posted_by' => $userId, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('journal_entry_lines')->insert([
                'journal_entry_id' => $journalId, 'gl_account_id' => $invoice->ar_account_id, 'department_id' => null,
                'description' => 'Accounts Receivable · '.$invoice->customer_name, 'debit' => $invoice->total_amount,
                'credit' => 0, 'foreign_currency' => $invoice->currency, 'foreign_debit' => $invoice->foreign_total_amount,
                'exchange_rate' => $invoice->exchange_rate, 'line_number' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $lineNumber = 2;
            foreach ($lines as $line) {
                DB::table('journal_entry_lines')->insert([
                    'journal_entry_id' => $journalId, 'gl_account_id' => $line->gl_account_id,
                    'department_id' => $line->department_id, 'description' => $line->description,
                    'debit' => 0, 'credit' => $line->amount, 'foreign_currency' => $invoice->currency,
                    'foreign_credit' => $line->foreign_amount, 'exchange_rate' => $invoice->exchange_rate, 'line_number' => $lineNumber++,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            if ((float) $invoice->tax_amount > 0) {
                DB::table('journal_entry_lines')->insert([
                    'journal_entry_id' => $journalId, 'gl_account_id' => $invoice->tax_account_id,
                    'department_id' => null, 'description' => 'Output tax '.$invoice->document_number,
                    'debit' => 0, 'credit' => $invoice->tax_amount, 'foreign_currency' => $invoice->currency,
                    'foreign_credit' => $invoice->foreign_tax_amount, 'exchange_rate' => $invoice->exchange_rate, 'line_number' => $lineNumber,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('ar_invoices')->where('id', $invoice->id)->update([
                'status' => 'posted', 'journal_entry_id' => $journalId, 'posted_by' => $userId,
                'posted_at' => now(), 'updated_at' => now(),
            ]);

            return $journalId;
        });
    }

    /** @param array<string, mixed> $data */
    public function createReceipt(int $companyId, ?int $branchId, int $userId, array $data): int
    {
        return DB::transaction(function () use ($companyId, $branchId, $userId, $data): int {
            $invoice = DB::table('ar_invoices')->where('company_id', $companyId)->where('id', $data['invoice_id'])
                ->whereIn('status', ['posted', 'partially_received'])->lockForUpdate()->first();
            if (! $invoice) {
                throw ValidationException::withMessages(['invoice_id' => 'Customer invoice tidak tersedia untuk penerimaan.']);
            }
            $foreignAmount = round((float) $data['amount'], 4);
            if ($foreignAmount <= 0 || $foreignAmount - (float) $invoice->foreign_outstanding_amount > .005) {
                throw ValidationException::withMessages(['amount' => 'Penerimaan melebihi outstanding invoice.']);
            }
            $rate = $this->currencyService->rate($companyId, $invoice->currency, $data['receipt_date']);
            $carrying = round((float) $invoice->carrying_amount * $foreignAmount / (float) $invoice->foreign_outstanding_amount, 4);
            $cash = $this->currencyService->toBase($foreignAmount, $rate);
            $fx = round($cash - $carrying, 4);
            $settings = DB::table('accounting_settings')->where('company_id', $companyId)->first();
            if (abs($fx) > .005 && ! ($fx > 0 ? $settings?->realized_fx_gain_account_id : $settings?->realized_fx_loss_account_id)) {
                throw ValidationException::withMessages(['currency' => 'Realized FX gain/loss accounts belum dikonfigurasi.']);
            }
            TransactionPeriodLock::ensureOpen($companyId, 'accounting', $data['receipt_date']);
            $this->assertAccount($companyId, (int) $data['cash_account_id'], ['asset']);
            $this->assertAccount($companyId, (int) $invoice->ar_account_id, ['asset']);

            $journalId = DB::table('journal_entries')->insertGetId([
                'company_id' => $companyId, 'branch_id' => $branchId,
                'document_number' => $this->nextNumber($companyId, 'ar_receipt_journal', 'RCJ', $data['receipt_date']),
                'journal_date' => $data['receipt_date'], 'source_type' => 'ar_receipt', 'status' => 'posted',
                'memo' => 'Customer Receipt · '.(($data['receipt_reference'] ?? null) ?: 'No reference'),
                'total_debit' => $cash + max(-$fx, 0), 'total_credit' => $carrying + max($fx, 0), 'created_by' => $userId,
                'posted_by' => $userId, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $journalLines = [
                ['journal_entry_id' => $journalId, 'gl_account_id' => $data['cash_account_id'], 'department_id' => null, 'description' => 'Cash / Bank receipt', 'debit' => $cash, 'credit' => 0, 'foreign_currency' => $invoice->currency, 'foreign_debit' => $foreignAmount, 'foreign_credit' => 0, 'exchange_rate' => $rate, 'line_number' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['journal_entry_id' => $journalId, 'gl_account_id' => $invoice->ar_account_id, 'department_id' => null, 'description' => 'Accounts Receivable settlement', 'debit' => 0, 'credit' => $carrying, 'foreign_currency' => $invoice->currency, 'foreign_debit' => 0, 'foreign_credit' => $foreignAmount, 'exchange_rate' => $rate, 'line_number' => 2, 'created_at' => now(), 'updated_at' => now()],
            ];
            if (abs($fx) > .005) {
                $journalLines[] = ['journal_entry_id' => $journalId, 'gl_account_id' => $fx > 0 ? $settings->realized_fx_gain_account_id : $settings->realized_fx_loss_account_id, 'department_id' => null, 'description' => 'Realized FX '.($fx > 0 ? 'gain' : 'loss'), 'debit' => max(-$fx, 0), 'credit' => max($fx, 0), 'foreign_currency' => null, 'foreign_debit' => 0, 'foreign_credit' => 0, 'exchange_rate' => null, 'line_number' => 3, 'created_at' => now(), 'updated_at' => now()];
            }
            DB::table('journal_entry_lines')->insert($journalLines);
            $receiptId = DB::table('ar_receipts')->insertGetId([
                'company_id' => $companyId, 'branch_id' => $branchId, 'customer_id' => $invoice->customer_id,
                'document_number' => $this->nextNumber($companyId, 'customer_receipt', 'RV', $data['receipt_date']),
                'receipt_date' => $data['receipt_date'], 'currency' => $invoice->currency, 'exchange_rate' => $rate,
                'foreign_amount' => $foreignAmount, 'realized_fx_amount' => $fx, 'status' => 'posted',
                'amount' => $cash, 'cash_account_id' => $data['cash_account_id'], 'ar_account_id' => $invoice->ar_account_id,
                'journal_entry_id' => $journalId, 'receipt_reference' => $data['receipt_reference'] ?? null,
                'notes' => $data['notes'] ?? null, 'created_by' => $userId, 'posted_by' => $userId,
                'posted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('ar_receipt_allocations')->insert([
                'ar_receipt_id' => $receiptId, 'ar_invoice_id' => $invoice->id, 'amount' => $carrying, 'foreign_amount' => $foreignAmount,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $received = round((float) $invoice->received_amount + $carrying, 4);
            $foreignOutstanding = max(0, round((float) $invoice->foreign_outstanding_amount - $foreignAmount, 4));
            $outstanding = max(0, round((float) $invoice->carrying_amount - $carrying, 4));
            DB::table('ar_invoices')->where('id', $invoice->id)->update([
                'received_amount' => $received, 'outstanding_amount' => $outstanding,
                'foreign_outstanding_amount' => $foreignOutstanding, 'carrying_amount' => $outstanding,
                'status' => $foreignOutstanding <= .005 ? 'received' : 'partially_received', 'updated_at' => now(),
            ]);

            return $receiptId;
        });
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
            throw ValidationException::withMessages(['account' => 'GL account tidak valid untuk posting Accounts Receivable.']);
        }
    }
}
