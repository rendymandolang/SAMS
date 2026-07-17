<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountsReceivableService
{
    /** @param array<string, mixed> $data */
    public function createInvoice(int $companyId, ?int $branchId, int $userId, array $data): int
    {
        return DB::transaction(function () use ($companyId, $branchId, $userId, $data): int {
            $subtotal = round(collect($data['lines'])->sum(fn (array $line): float => (float) $line['quantity'] * (float) $line['unit_price']), 4);
            $tax = round((float) ($data['tax_amount'] ?? 0), 4);
            $total = $subtotal + $tax;
            $invoiceId = DB::table('ar_invoices')->insertGetId([
                'company_id' => $companyId, 'branch_id' => $branchId, 'customer_id' => $data['customer_id'],
                'document_number' => $this->nextNumber($companyId, 'customer_invoice', 'CI', $data['invoice_date']),
                'customer_reference' => $data['customer_reference'] ?? null, 'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'], 'currency' => $data['currency'], 'status' => 'draft',
                'subtotal' => $subtotal, 'tax_amount' => $tax, 'total_amount' => $total, 'received_amount' => 0,
                'outstanding_amount' => $total, 'ar_account_id' => $data['ar_account_id'],
                'tax_account_id' => $data['tax_account_id'] ?? null, 'notes' => $data['notes'] ?? null,
                'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($data['lines'] as $index => $line) {
                DB::table('ar_invoice_lines')->insert([
                    'ar_invoice_id' => $invoiceId, 'gl_account_id' => $line['gl_account_id'],
                    'department_id' => $line['department_id'] ?? null, 'description' => trim($line['description']),
                    'quantity' => $line['quantity'], 'unit_price' => $line['unit_price'],
                    'amount' => round((float) $line['quantity'] * (float) $line['unit_price'], 4),
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
                'credit' => 0, 'line_number' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $lineNumber = 2;
            foreach ($lines as $line) {
                DB::table('journal_entry_lines')->insert([
                    'journal_entry_id' => $journalId, 'gl_account_id' => $line->gl_account_id,
                    'department_id' => $line->department_id, 'description' => $line->description,
                    'debit' => 0, 'credit' => $line->amount, 'line_number' => $lineNumber++,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            if ((float) $invoice->tax_amount > 0) {
                DB::table('journal_entry_lines')->insert([
                    'journal_entry_id' => $journalId, 'gl_account_id' => $invoice->tax_account_id,
                    'department_id' => null, 'description' => 'Output tax '.$invoice->document_number,
                    'debit' => 0, 'credit' => $invoice->tax_amount, 'line_number' => $lineNumber,
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
            $amount = round((float) $data['amount'], 4);
            if ($amount <= 0 || $amount - (float) $invoice->outstanding_amount > .005) {
                throw ValidationException::withMessages(['amount' => 'Penerimaan melebihi outstanding invoice.']);
            }
            TransactionPeriodLock::ensureOpen($companyId, 'accounting', $data['receipt_date']);
            $this->assertAccount($companyId, (int) $data['cash_account_id'], ['asset']);
            $this->assertAccount($companyId, (int) $invoice->ar_account_id, ['asset']);

            $journalId = DB::table('journal_entries')->insertGetId([
                'company_id' => $companyId, 'branch_id' => $branchId,
                'document_number' => $this->nextNumber($companyId, 'ar_receipt_journal', 'RCJ', $data['receipt_date']),
                'journal_date' => $data['receipt_date'], 'source_type' => 'ar_receipt', 'status' => 'posted',
                'memo' => 'Customer Receipt · '.(($data['receipt_reference'] ?? null) ?: 'No reference'),
                'total_debit' => $amount, 'total_credit' => $amount, 'created_by' => $userId,
                'posted_by' => $userId, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('journal_entry_lines')->insert([
                ['journal_entry_id' => $journalId, 'gl_account_id' => $data['cash_account_id'], 'department_id' => null, 'description' => 'Cash / Bank receipt', 'debit' => $amount, 'credit' => 0, 'line_number' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['journal_entry_id' => $journalId, 'gl_account_id' => $invoice->ar_account_id, 'department_id' => null, 'description' => 'Accounts Receivable settlement', 'debit' => 0, 'credit' => $amount, 'line_number' => 2, 'created_at' => now(), 'updated_at' => now()],
            ]);
            $receiptId = DB::table('ar_receipts')->insertGetId([
                'company_id' => $companyId, 'branch_id' => $branchId, 'customer_id' => $invoice->customer_id,
                'document_number' => $this->nextNumber($companyId, 'customer_receipt', 'RV', $data['receipt_date']),
                'receipt_date' => $data['receipt_date'], 'currency' => $data['currency'], 'status' => 'posted',
                'amount' => $amount, 'cash_account_id' => $data['cash_account_id'], 'ar_account_id' => $invoice->ar_account_id,
                'journal_entry_id' => $journalId, 'receipt_reference' => $data['receipt_reference'] ?? null,
                'notes' => $data['notes'] ?? null, 'created_by' => $userId, 'posted_by' => $userId,
                'posted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('ar_receipt_allocations')->insert([
                'ar_receipt_id' => $receiptId, 'ar_invoice_id' => $invoice->id, 'amount' => $amount,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $received = round((float) $invoice->received_amount + $amount, 4);
            $outstanding = max(0, round((float) $invoice->total_amount - $received, 4));
            DB::table('ar_invoices')->where('id', $invoice->id)->update([
                'received_amount' => $received, 'outstanding_amount' => $outstanding,
                'status' => $outstanding <= .005 ? 'received' : 'partially_received', 'updated_at' => now(),
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
