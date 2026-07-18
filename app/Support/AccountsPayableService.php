<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountsPayableService
{
    public function __construct(private readonly AccountingCurrencyService $currencyService) {}

    /** @param array<string, mixed> $data */
    public function createInvoice(int $companyId, ?int $branchId, int $userId, array $data): int
    {
        return DB::transaction(function () use ($companyId, $branchId, $userId, $data): int {
            $rate = $this->currencyService->rate($companyId, $data['currency'], $data['invoice_date']);
            $foreignSubtotal = round(collect($data['lines'])->sum(fn (array $line): float => (float) $line['quantity'] * (float) $line['unit_price']), 4);
            $taxCode = empty($data['tax_code_id']) ? null : DB::table('accounting_tax_codes')->where('company_id', $companyId)->where('id', $data['tax_code_id'])->where('type', 'purchase')->where('is_active', true)->firstOrFail();
            $withholdingCode = empty($data['withholding_tax_code_id']) ? null : DB::table('accounting_tax_codes')->where('company_id', $companyId)->where('id', $data['withholding_tax_code_id'])->where('type', 'withholding')->where('is_active', true)->firstOrFail();
            $foreignTax = $taxCode ? round($foreignSubtotal * (float) $taxCode->rate_percent / 100, 4) : round((float) ($data['tax_amount'] ?? 0), 4);
            $foreignWithholding = $withholdingCode ? round($foreignSubtotal * (float) $withholdingCode->rate_percent / 100, 4) : 0.0;
            $foreignTotal = round($foreignSubtotal + $foreignTax - $foreignWithholding, 4);
            $subtotal = $this->currencyService->toBase($foreignSubtotal, $rate);
            $tax = $this->currencyService->toBase($foreignTax, $rate);
            $withholding = $this->currencyService->toBase($foreignWithholding, $rate);
            $gross = round($subtotal + $tax, 4);
            $total = round($gross - $withholding, 4);
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
                'exchange_rate' => $rate,
                'foreign_subtotal' => $foreignSubtotal,
                'foreign_tax_amount' => $foreignTax,
                'foreign_withholding_amount' => $foreignWithholding,
                'foreign_total_amount' => $foreignTotal,
                'foreign_outstanding_amount' => $foreignTotal,
                'carrying_amount' => $total,
                'status' => 'draft',
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'withholding_amount' => $withholding,
                'gross_amount' => $gross,
                'total_amount' => $total,
                'paid_amount' => 0,
                'outstanding_amount' => $total,
                'ap_account_id' => $data['ap_account_id'],
                'tax_account_id' => $taxCode?->gl_account_id ?? ($data['tax_account_id'] ?? null),
                'tax_code_id' => $taxCode?->id,
                'withholding_tax_code_id' => $withholdingCode?->id,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($data['lines'] as $index => $line) {
                $foreignAmount = round((float) $line['quantity'] * (float) $line['unit_price'], 4);
                $amount = $this->currencyService->toBase($foreignAmount, $rate);
                DB::table('ap_invoice_lines')->insert([
                    'ap_invoice_id' => $invoiceId,
                    'purchase_order_item_id' => $line['purchase_order_item_id'] ?? null,
                    'goods_receipt_item_id' => $line['goods_receipt_item_id'] ?? null,
                    'gl_account_id' => $line['gl_account_id'],
                    'department_id' => $line['department_id'] ?? null,
                    'description' => trim($line['description']),
                    'quantity' => $line['quantity'],
                    'unit_price' => $amount / (float) $line['quantity'],
                    'foreign_unit_price' => $line['unit_price'],
                    'foreign_amount' => $foreignAmount,
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
            $this->validateThreeWayMatch($companyId, $invoice, $lines);
            $this->assertPostingAccount($companyId, (int) $invoice->ap_account_id, ['liability']);
            if ((float) $invoice->tax_amount > 0) {
                $this->assertPostingAccount($companyId, (int) $invoice->tax_account_id, ['asset', 'expense']);
            }
            if ((float) $invoice->withholding_amount > 0) {
                $withholdingAccount = DB::table('accounting_tax_codes')->where('company_id', $companyId)->where('id', $invoice->withholding_tax_code_id)->where('type', 'withholding')->value('gl_account_id');
                $this->assertPostingAccount($companyId, (int) $withholdingAccount, ['liability']);
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
                'total_debit' => $invoice->gross_amount > 0 ? $invoice->gross_amount : $invoice->total_amount,
                'total_credit' => $invoice->gross_amount > 0 ? $invoice->gross_amount : $invoice->total_amount,
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
                    'foreign_currency' => $invoice->currency,
                    'foreign_debit' => $line->foreign_amount,
                    'exchange_rate' => $invoice->exchange_rate,
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
                    'foreign_currency' => $invoice->currency,
                    'foreign_debit' => $invoice->foreign_tax_amount,
                    'exchange_rate' => $invoice->exchange_rate,
                    'line_number' => $lineNumber++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if ((float) $invoice->withholding_amount > 0) {
                DB::table('journal_entry_lines')->insert([
                    'journal_entry_id' => $journalId, 'gl_account_id' => $withholdingAccount, 'department_id' => null,
                    'description' => 'Withholding tax '.$invoice->supplier_invoice_number, 'debit' => 0,
                    'credit' => $invoice->withholding_amount, 'foreign_currency' => $invoice->currency,
                    'foreign_credit' => $invoice->foreign_withholding_amount, 'exchange_rate' => $invoice->exchange_rate,
                    'line_number' => $lineNumber++, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('journal_entry_lines')->insert([
                'journal_entry_id' => $journalId,
                'gl_account_id' => $invoice->ap_account_id,
                'department_id' => null,
                'description' => 'Accounts Payable · '.$invoice->supplier_name,
                'debit' => 0,
                'credit' => $invoice->total_amount,
                'foreign_currency' => $invoice->currency,
                'foreign_credit' => $invoice->foreign_total_amount,
                'exchange_rate' => $invoice->exchange_rate,
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

            $foreignTotal = 0.0;
            $carryingTotal = 0.0;
            $cashTotal = 0.0;
            $computed = [];
            $paymentRate = $this->currencyService->rate($companyId, $data['currency'], $data['payment_date']);
            foreach ($allocations as $allocation) {
                $invoice = $invoices->get($allocation['invoice_id']);
                $foreignAmount = round((float) $allocation['amount'], 4);
                if ($invoice->currency !== $data['currency'] || $foreignAmount <= 0 || $foreignAmount - (float) $invoice->foreign_outstanding_amount > .005) {
                    throw ValidationException::withMessages(['allocations' => 'Alokasi pembayaran melebihi outstanding invoice.']);
                }
                if ((int) $invoice->ap_account_id !== (int) $data['ap_account_id']) {
                    throw ValidationException::withMessages(['ap_account_id' => 'Semua invoice harus menggunakan Accounts Payable account yang sama.']);
                }
                $carrying = round((float) $invoice->carrying_amount * $foreignAmount / (float) $invoice->foreign_outstanding_amount, 4);
                $cash = $this->currencyService->toBase($foreignAmount, $paymentRate);
                $computed[] = compact('invoice', 'foreignAmount', 'carrying', 'cash');
                $foreignTotal += $foreignAmount;
                $carryingTotal += $carrying;
                $cashTotal += $cash;
            }
            $foreignTotal = round($foreignTotal, 4);
            $carryingTotal = round($carryingTotal, 4);
            $cashTotal = round($cashTotal, 4);
            $fx = round($cashTotal - $carryingTotal, 4);
            $settings = DB::table('accounting_settings')->where('company_id', $companyId)->first();
            if (abs($fx) > .005 && ! ($fx > 0 ? $settings?->realized_fx_loss_account_id : $settings?->realized_fx_gain_account_id)) {
                throw ValidationException::withMessages(['currency' => 'Realized FX gain/loss accounts belum dikonfigurasi.']);
            }
            TransactionPeriodLock::ensureOpen($companyId, 'accounting', $data['payment_date']);

            $journalId = DB::table('journal_entries')->insertGetId([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'document_number' => $this->nextNumber($companyId, 'ap_payment_journal', 'PAYJ', $data['payment_date']),
                'journal_date' => $data['payment_date'],
                'source_type' => 'ap_payment',
                'status' => 'posted',
                'memo' => 'Supplier Payment · '.(($data['payment_reference'] ?? null) ?: 'No reference'),
                'total_debit' => $carryingTotal + max($fx, 0),
                'total_credit' => $cashTotal + max(-$fx, 0),
                'created_by' => $userId,
                'posted_by' => $userId,
                'posted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $journalLines = [
                ['journal_entry_id' => $journalId, 'gl_account_id' => $data['ap_account_id'], 'department_id' => null, 'description' => 'Accounts Payable settlement', 'debit' => $carryingTotal, 'credit' => 0, 'foreign_currency' => $data['currency'], 'foreign_debit' => $foreignTotal, 'foreign_credit' => 0, 'exchange_rate' => $paymentRate, 'line_number' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['journal_entry_id' => $journalId, 'gl_account_id' => $data['cash_account_id'], 'department_id' => null, 'description' => 'Cash / Bank payment', 'debit' => 0, 'credit' => $cashTotal, 'foreign_currency' => $data['currency'], 'foreign_debit' => 0, 'foreign_credit' => $foreignTotal, 'exchange_rate' => $paymentRate, 'line_number' => 2, 'created_at' => now(), 'updated_at' => now()],
            ];
            if (abs($fx) > .005) {
                $journalLines[] = ['journal_entry_id' => $journalId, 'gl_account_id' => $fx > 0 ? $settings->realized_fx_loss_account_id : $settings->realized_fx_gain_account_id, 'department_id' => null, 'description' => 'Realized FX '.($fx > 0 ? 'loss' : 'gain'), 'debit' => max($fx, 0), 'credit' => max(-$fx, 0), 'foreign_currency' => null, 'foreign_debit' => 0, 'foreign_credit' => 0, 'exchange_rate' => null, 'line_number' => 3, 'created_at' => now(), 'updated_at' => now()];
            }
            DB::table('journal_entry_lines')->insert($journalLines);

            $paymentId = DB::table('ap_payments')->insertGetId([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'supplier_id' => $data['supplier_id'],
                'document_number' => $this->nextNumber($companyId, 'supplier_payment', 'PV', $data['payment_date']),
                'payment_date' => $data['payment_date'],
                'currency' => $data['currency'],
                'exchange_rate' => $paymentRate,
                'foreign_amount' => $foreignTotal,
                'realized_fx_amount' => $fx,
                'status' => 'posted',
                'amount' => $cashTotal,
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

            foreach ($computed as $allocation) {
                extract($allocation);
                $paid = round((float) $invoice->paid_amount + $carrying, 4);
                $foreignOutstanding = max(0, round((float) $invoice->foreign_outstanding_amount - $foreignAmount, 4));
                $carryingOutstanding = max(0, round((float) $invoice->carrying_amount - $carrying, 4));
                DB::table('ap_payment_allocations')->insert([
                    'ap_payment_id' => $paymentId,
                    'ap_invoice_id' => $invoice->id,
                    'amount' => $carrying,
                    'foreign_amount' => $foreignAmount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('ap_invoices')->where('id', $invoice->id)->update([
                    'paid_amount' => $paid,
                    'outstanding_amount' => $carryingOutstanding,
                    'foreign_outstanding_amount' => $foreignOutstanding,
                    'carrying_amount' => $carryingOutstanding,
                    'status' => $foreignOutstanding <= .005 ? 'paid' : 'partially_paid',
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

    private function validateThreeWayMatch(int $companyId, object $invoice, $lines): void
    {
        if (! $invoice->purchase_order_id) {
            return;
        }
        $settings = DB::table('accounting_settings')->where('company_id', $companyId)->first();
        $priceTolerance = (float) ($settings->po_price_tolerance_percent ?? 2);
        $quantityTolerance = (float) ($settings->po_quantity_tolerance_percent ?? 0);
        foreach ($lines as $line) {
            if (! $line->purchase_order_item_id || ! $line->goods_receipt_item_id) {
                throw ValidationException::withMessages(['matching' => 'Setiap line invoice berbasis PO wajib memiliki PO item dan posted Goods Receipt item.']);
            }
            $poItem = DB::table('purchase_order_items')->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
                ->where('purchase_orders.company_id', $companyId)->where('purchase_orders.id', $invoice->purchase_order_id)
                ->where('purchase_order_items.id', $line->purchase_order_item_id)->select('purchase_order_items.*')->first();
            $receiptItem = DB::table('goods_receipt_items')->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')
                ->where('goods_receipts.company_id', $companyId)->where('goods_receipts.purchase_order_id', $invoice->purchase_order_id)
                ->where('goods_receipts.status', 'posted')->where('goods_receipt_items.id', $line->goods_receipt_item_id)
                ->where('goods_receipt_items.purchase_order_item_id', $line->purchase_order_item_id)->select('goods_receipt_items.*')->first();
            if (! $poItem || ! $receiptItem) {
                throw ValidationException::withMessages(['matching' => 'PO item atau Goods Receipt item tidak valid untuk invoice ini.']);
            }
            $alreadyInvoiced = (float) DB::table('ap_invoice_lines')->join('ap_invoices', 'ap_invoices.id', '=', 'ap_invoice_lines.ap_invoice_id')
                ->where('ap_invoices.company_id', $companyId)->where('ap_invoice_lines.goods_receipt_item_id', $receiptItem->id)
                ->where('ap_invoices.id', '!=', $invoice->id)->whereIn('ap_invoices.status', ['posted', 'partially_paid', 'paid'])
                ->sum('ap_invoice_lines.quantity');
            $priceVariance = (float) $poItem->unit_price > 0 ? abs(((float) $line->unit_price - (float) $poItem->unit_price) / (float) $poItem->unit_price * 100) : 0;
            $allowedQuantity = (float) $receiptItem->accepted_quantity * (1 + $quantityTolerance / 100) - $alreadyInvoiced;
            $quantityVariance = (float) $line->quantity - ((float) $receiptItem->accepted_quantity - $alreadyInvoiced);
            if ($priceVariance - $priceTolerance > .0001 || (float) $line->quantity - $allowedQuantity > .0001) {
                throw ValidationException::withMessages(['matching' => 'Three-way match gagal: price atau quantity invoice melebihi tolerance perusahaan.']);
            }
            DB::table('ap_invoice_lines')->where('id', $line->id)->update([
                'matching_status' => 'passed', 'price_variance_percent' => round($priceVariance, 4),
                'quantity_variance' => round($quantityVariance, 4), 'updated_at' => now(),
            ]);
        }
    }
}
