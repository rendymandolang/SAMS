<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountsPayableService
{
    /** @param array<string, mixed> $data */
    public function createInvoice(int $companyId, ?int $branchId, int $userId, array $data): int
    {
        return DB::transaction(function () use ($companyId, $branchId, $userId, $data): int {
            $subtotal = round(collect($data['lines'])->sum(fn (array $line): float => (float) $line['quantity'] * (float) $line['unit_price']), 4);
            $tax = round((float) ($data['tax_amount'] ?? 0), 4);
            $total = $subtotal + $tax;
            $invoiceId = DB::table('ap_invoices')->insertGetId([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'supplier_id' => $data['supplier_id'],
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'document_number' => $this->nextNumber($companyId, 'supplier_invoice', 'SI', $data['invoice_date']),
                'supplier_invoice_number' => trim($data['supplier_invoice_number']),
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'],
                'currency' => $data['currency'],
                'status' => 'draft',
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'paid_amount' => 0,
                'outstanding_amount' => $total,
                'ap_account_id' => $data['ap_account_id'],
                'tax_account_id' => $data['tax_account_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($data['lines'] as $index => $line) {
                $amount = round((float) $line['quantity'] * (float) $line['unit_price'], 4);
                DB::table('ap_invoice_lines')->insert([
                    'ap_invoice_id' => $invoiceId,
                    'gl_account_id' => $line['gl_account_id'],
                    'department_id' => $line['department_id'] ?? null,
                    'description' => trim($line['description']),
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'amount' => $amount,
                    'line_number' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $invoiceId;
        });
    }

    public function postInvoice(int $companyId, int $invoiceId, int $userId): int
    {
        return DB::transaction(function () use ($companyId, $invoiceId, $userId): int {
            $invoice = DB::table('ap_invoices')
                ->join('suppliers', 'suppliers.id', '=', 'ap_invoices.supplier_id')
                ->where('ap_invoices.company_id', $companyId)
                ->where('ap_invoices.id', $invoiceId)
                ->select('ap_invoices.*', 'suppliers.name as supplier_name')
                ->lockForUpdate()
                ->first();
            abort_unless($invoice, 404);
            abort_if($invoice->status !== 'draft', 422, 'Supplier invoice sudah diposting.');
            TransactionPeriodLock::ensureOpen($companyId, 'accounting', $invoice->invoice_date);
            $lines = DB::table('ap_invoice_lines')->where('ap_invoice_id', $invoice->id)->orderBy('line_number')->get();
            abort_if($lines->isEmpty(), 422, 'Supplier invoice tidak memiliki detail.');
            $this->assertPostingAccount($companyId, (int) $invoice->ap_account_id, ['liability']);
            if ((float) $invoice->tax_amount > 0) {
                $this->assertPostingAccount($companyId, (int) $invoice->tax_account_id, ['asset', 'expense']);
            }
            foreach ($lines->pluck('gl_account_id')->unique() as $accountId) {
                $this->assertPostingAccount($companyId, (int) $accountId, ['asset', 'expense']);
            }

            $journalId = DB::table('journal_entries')->insertGetId([
                'company_id' => $companyId,
                'branch_id' => $invoice->branch_id,
                'document_number' => $this->nextNumber($companyId, 'ap_invoice_journal', 'APJ', $invoice->invoice_date),
                'journal_date' => $invoice->invoice_date,
                'source_type' => 'ap_invoice',
                'status' => 'posted',
                'memo' => 'Supplier Invoice '.$invoice->document_number.' · '.$invoice->supplier_name,
                'total_debit' => $invoice->total_amount,
                'total_credit' => $invoice->total_amount,
                'created_by' => $userId,
                'posted_by' => $userId,
                'posted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $lineNumber = 1;
            foreach ($lines as $line) {
                DB::table('journal_entry_lines')->insert([
                    'journal_entry_id' => $journalId,
                    'gl_account_id' => $line->gl_account_id,
                    'department_id' => $line->department_id,
                    'description' => $line->description,
                    'debit' => $line->amount,
                    'credit' => 0,
                    'line_number' => $lineNumber++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if ((float) $invoice->tax_amount > 0) {
                DB::table('journal_entry_lines')->insert([
                    'journal_entry_id' => $journalId,
                    'gl_account_id' => $invoice->tax_account_id,
                    'department_id' => null,
                    'description' => 'Input tax '.$invoice->supplier_invoice_number,
                    'debit' => $invoice->tax_amount,
                    'credit' => 0,
                    'line_number' => $lineNumber++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('journal_entry_lines')->insert([
                'journal_entry_id' => $journalId,
                'gl_account_id' => $invoice->ap_account_id,
                'department_id' => null,
                'description' => 'Accounts Payable · '.$invoice->supplier_name,
                'debit' => 0,
                'credit' => $invoice->total_amount,
                'line_number' => $lineNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('ap_invoices')->where('id', $invoice->id)->update([
                'status' => 'posted',
                'journal_entry_id' => $journalId,
                'posted_by' => $userId,
                'posted_at' => now(),
                'updated_at' => now(),
            ]);

            return $journalId;
        });
    }

    /** @param array<int, array{invoice_id:int, amount:mixed}> $allocations */
    public function createPayment(int $companyId, ?int $branchId, int $userId, array $data, array $allocations): int
    {
        return DB::transaction(function () use ($companyId, $branchId, $userId, $data, $allocations): int {
            $invoiceIds = collect($allocations)->pluck('invoice_id')->unique();
            $invoices = DB::table('ap_invoices')
                ->where('company_id', $companyId)
                ->where('supplier_id', $data['supplier_id'])
                ->whereIn('status', ['posted', 'partially_paid'])
                ->whereIn('id', $invoiceIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            if ($invoices->count() !== $invoiceIds->count()) {
                throw ValidationException::withMessages(['allocations' => 'Invoice terbuka tidak valid untuk supplier ini.']);
            }

            $total = 0.0;
            foreach ($allocations as $allocation) {
                $invoice = $invoices->get($allocation['invoice_id']);
                $amount = round((float) $allocation['amount'], 4);
                if ($amount <= 0 || $amount - (float) $invoice->outstanding_amount > .005) {
                    throw ValidationException::withMessages(['allocations' => 'Alokasi pembayaran melebihi outstanding invoice.']);
                }
                if ((int) $invoice->ap_account_id !== (int) $data['ap_account_id']) {
                    throw ValidationException::withMessages(['ap_account_id' => 'Semua invoice harus menggunakan Accounts Payable account yang sama.']);
                }
                $total += $amount;
            }
            $total = round($total, 4);
            TransactionPeriodLock::ensureOpen($companyId, 'accounting', $data['payment_date']);

            $journalId = DB::table('journal_entries')->insertGetId([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'document_number' => $this->nextNumber($companyId, 'ap_payment_journal', 'PAYJ', $data['payment_date']),
                'journal_date' => $data['payment_date'],
                'source_type' => 'ap_payment',
                'status' => 'posted',
                'memo' => 'Supplier Payment · '.(($data['payment_reference'] ?? null) ?: 'No reference'),
                'total_debit' => $total,
                'total_credit' => $total,
                'created_by' => $userId,
                'posted_by' => $userId,
                'posted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('journal_entry_lines')->insert([
                ['journal_entry_id' => $journalId, 'gl_account_id' => $data['ap_account_id'], 'department_id' => null, 'description' => 'Accounts Payable settlement', 'debit' => $total, 'credit' => 0, 'line_number' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['journal_entry_id' => $journalId, 'gl_account_id' => $data['cash_account_id'], 'department_id' => null, 'description' => 'Cash / Bank payment', 'debit' => 0, 'credit' => $total, 'line_number' => 2, 'created_at' => now(), 'updated_at' => now()],
            ]);

            $paymentId = DB::table('ap_payments')->insertGetId([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'supplier_id' => $data['supplier_id'],
                'document_number' => $this->nextNumber($companyId, 'supplier_payment', 'PV', $data['payment_date']),
                'payment_date' => $data['payment_date'],
                'currency' => $data['currency'],
                'status' => 'posted',
                'amount' => $total,
                'cash_account_id' => $data['cash_account_id'],
                'ap_account_id' => $data['ap_account_id'],
                'journal_entry_id' => $journalId,
                'payment_reference' => ($data['payment_reference'] ?? null) ?: null,
                'notes' => ($data['notes'] ?? null) ?: null,
                'created_by' => $userId,
                'posted_by' => $userId,
                'posted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($allocations as $allocation) {
                $invoice = $invoices->get($allocation['invoice_id']);
                $amount = round((float) $allocation['amount'], 4);
                $paid = round((float) $invoice->paid_amount + $amount, 4);
                $outstanding = max(0, round((float) $invoice->total_amount - $paid, 4));
                DB::table('ap_payment_allocations')->insert([
                    'ap_payment_id' => $paymentId,
                    'ap_invoice_id' => $invoice->id,
                    'amount' => $amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('ap_invoices')->where('id', $invoice->id)->update([
                    'paid_amount' => $paid,
                    'outstanding_amount' => $outstanding,
                    'status' => $outstanding <= .005 ? 'paid' : 'partially_paid',
                    'updated_at' => now(),
                ]);
            }

            return $paymentId;
        });
    }

    private function nextNumber(int $companyId, string $type, string $prefix, string $date): string
    {
        $period = date('Ym', strtotime($date));
        DB::table('accounting_subledger_sequences')->insertOrIgnore([
            'company_id' => $companyId,
            'document_type' => $type,
            'period' => $period,
            'next_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sequence = DB::table('accounting_subledger_sequences')
            ->where('company_id', $companyId)
            ->where('document_type', $type)
            ->where('period', $period)
            ->lockForUpdate()
            ->firstOrFail();
        DB::table('accounting_subledger_sequences')->where('id', $sequence->id)->update([
            'next_number' => (int) $sequence->next_number + 1,
            'updated_at' => now(),
        ]);

        return $prefix.'-'.$period.'-'.str_pad((string) $sequence->next_number, 5, '0', STR_PAD_LEFT);
    }

    private function assertPostingAccount(int $companyId, int $accountId, array $types): void
    {
        $valid = DB::table('gl_accounts')
            ->where('company_id', $companyId)
            ->where('id', $accountId)
            ->whereIn('type', $types)
            ->where('is_active', true)
            ->where('allow_posting', true)
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages(['account' => 'GL account supplier invoice tidak lagi valid untuk posting.']);
        }
    }
}
