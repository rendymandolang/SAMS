<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdvancedAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_tax_codes_calculate_purchase_sales_and_withholding_journals(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $expense = DB::table('gl_accounts')->where('code', '6100')->firstOrFail();
        $inputTax = DB::table('gl_accounts')->where('code', '1300')->firstOrFail();
        $taxPayable = DB::table('gl_accounts')->where('code', '2200')->firstOrFail();
        $payable = DB::table('gl_accounts')->where('code', '2100')->firstOrFail();
        $receivable = DB::table('gl_accounts')->where('code', '1200')->firstOrFail();
        $revenue = DB::table('gl_accounts')->where('code', '4100')->firstOrFail();
        $supplier = DB::table('suppliers')->where('is_active', true)->firstOrFail();

        foreach ([
            ['code' => 'PPN-IN', 'name' => 'Input VAT', 'type' => 'purchase', 'rate_percent' => 11, 'gl_account_id' => $inputTax->id],
            ['code' => 'PPH-23', 'name' => 'Withholding 23', 'type' => 'withholding', 'rate_percent' => 2, 'gl_account_id' => $taxPayable->id],
            ['code' => 'PPN-OUT', 'name' => 'Output VAT', 'type' => 'sales', 'rate_percent' => 11, 'gl_account_id' => $taxPayable->id],
        ] as $tax) {
            $this->actingAs($finance)->post('/accounting/configuration/tax-codes', $tax)->assertRedirect();
        }
        $purchaseTax = DB::table('accounting_tax_codes')->where('code', 'PPN-IN')->firstOrFail();
        $withholdingTax = DB::table('accounting_tax_codes')->where('code', 'PPH-23')->firstOrFail();
        $salesTax = DB::table('accounting_tax_codes')->where('code', 'PPN-OUT')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/configuration/posting-rules', ['transaction_type' => 'ap_invoice', 'account_role' => 'payable', 'gl_account_id' => $payable->id])->assertRedirect();
        $this->assertDatabaseHas('accounting_posting_rules', ['transaction_type' => 'ap_invoice', 'account_role' => 'payable', 'gl_account_id' => $payable->id]);
        $this->actingAs($finance)->get('/accounting/configuration')->assertOk()->assertSee('PPN-IN')->assertSee('Configurable Posting Rules');

        $this->actingAs($finance)->post('/accounting/payables', [
            'supplier_id' => $supplier->id, 'supplier_invoice_number' => 'TAX-AP-01', 'invoice_date' => today()->toDateString(),
            'due_date' => today()->addDays(30)->toDateString(), 'currency' => 'IDR', 'ap_account_id' => $payable->id,
            'tax_code_id' => $purchaseTax->id, 'withholding_tax_code_id' => $withholdingTax->id,
            'lines' => [['gl_account_id' => $expense->id, 'description' => 'Taxed service', 'quantity' => 1, 'unit_price' => 1000000]],
        ])->assertRedirect();
        $ap = DB::table('ap_invoices')->firstOrFail();
        $this->assertSame(110000.0, (float) $ap->tax_amount);
        $this->assertSame(20000.0, (float) $ap->withholding_amount);
        $this->assertSame(1090000.0, (float) $ap->total_amount);
        $this->actingAs($finance)->post('/accounting/payables/'.$ap->id.'/post')->assertRedirect();
        $apJournal = DB::table('journal_entries')->where('source_type', 'ap_invoice')->firstOrFail();
        $this->assertSame(1110000.0, (float) $apJournal->total_debit);
        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $apJournal->id, 'gl_account_id' => $taxPayable->id, 'credit' => 20000]);

        $this->actingAs($finance)->post('/accounting/customers', ['code' => 'TAX-CUST', 'name' => 'Tax Customer', 'payment_terms_days' => 30]);
        $customer = DB::table('accounting_customers')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/receivables', [
            'customer_id' => $customer->id, 'invoice_date' => today()->toDateString(), 'due_date' => today()->addDays(30)->toDateString(),
            'currency' => 'IDR', 'ar_account_id' => $receivable->id, 'tax_code_id' => $salesTax->id,
            'lines' => [['gl_account_id' => $revenue->id, 'description' => 'Taxed revenue', 'quantity' => 1, 'unit_price' => 1000000]],
        ])->assertRedirect();
        $ar = DB::table('ar_invoices')->firstOrFail();
        $this->assertSame(110000.0, (float) $ar->tax_amount);
        $this->assertSame(1110000.0, (float) $ar->total_amount);
        $this->actingAs($finance)->post('/accounting/receivables/'.$ar->id.'/post')->assertRedirect();
        $this->assertDatabaseHas('journal_entry_lines', ['gl_account_id' => $taxPayable->id, 'credit' => 110000]);
        $this->actingAs($finance)->post('/accounting/configuration/tax-codes/'.$salesTax->id.'/toggle')->assertRedirect();
        $this->assertDatabaseHas('accounting_tax_codes', ['id' => $salesTax->id, 'is_active' => false]);
    }

    public function test_po_invoice_requires_posted_receipt_and_company_tolerance(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $expense = DB::table('gl_accounts')->where('code', '6100')->firstOrFail();
        $payable = DB::table('gl_accounts')->where('code', '2100')->firstOrFail();
        $supplier = DB::table('suppliers')->where('is_active', true)->firstOrFail();
        $branch = DB::table('branches')->firstOrFail();
        $item = DB::table('items')->firstOrFail();
        $unit = DB::table('units')->firstOrFail();
        $storage = DB::table('storage_locations')->firstOrFail();
        $companyId = (int) $branch->company_id;
        $this->actingAs($finance)->post('/accounting/configuration/settings', ['po_price_tolerance_percent' => 2, 'po_quantity_tolerance_percent' => 0]);
        $poId = DB::table('purchase_orders')->insertGetId(['company_id' => $companyId, 'branch_id' => $branch->id, 'supplier_id' => $supplier->id, 'purchase_request_id' => null, 'created_by' => $finance->id, 'document_number' => 'PO-MATCH-01', 'order_date' => today(), 'expected_date' => today(), 'status' => 'received', 'currency' => 'IDR', 'subtotal' => 1000, 'discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => 1000, 'notes' => null, 'created_at' => now(), 'updated_at' => now()]);
        $poItemId = DB::table('purchase_order_items')->insertGetId(['purchase_order_id' => $poId, 'purchase_request_item_id' => null, 'item_id' => $item->id, 'unit_id' => $unit->id, 'quantity' => 10, 'received_quantity' => 10, 'unit_price' => 100, 'discount_amount' => 0, 'tax_amount' => 0, 'line_total' => 1000, 'created_at' => now(), 'updated_at' => now()]);
        $grId = DB::table('goods_receipts')->insertGetId(['company_id' => $companyId, 'branch_id' => $branch->id, 'purchase_order_id' => $poId, 'storage_location_id' => $storage->id, 'received_by' => $finance->id, 'document_number' => 'GR-MATCH-01', 'received_at' => now(), 'supplier_delivery_number' => 'DO-1', 'status' => 'posted', 'notes' => null, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $grItemId = DB::table('goods_receipt_items')->insertGetId(['goods_receipt_id' => $grId, 'purchase_order_item_id' => $poItemId, 'item_id' => $item->id, 'unit_id' => $unit->id, 'quantity' => 10, 'accepted_quantity' => 10, 'rejected_quantity' => 0, 'unit_cost' => 100, 'created_at' => now(), 'updated_at' => now()]);

        $payload = ['supplier_id' => $supplier->id, 'purchase_order_id' => $poId, 'supplier_invoice_number' => 'MATCH-01', 'invoice_date' => today()->toDateString(), 'due_date' => today()->addDays(30)->toDateString(), 'currency' => 'IDR', 'ap_account_id' => $payable->id, 'lines' => [['gl_account_id' => $expense->id, 'purchase_order_item_id' => $poItemId, 'goods_receipt_item_id' => $grItemId, 'description' => 'Matched item', 'quantity' => 5, 'unit_price' => 101]]];
        $this->actingAs($finance)->post('/accounting/payables', $payload)->assertRedirect();
        $invoice = DB::table('ap_invoices')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/payables/'.$invoice->id.'/post')->assertRedirect();
        $this->assertDatabaseHas('ap_invoice_lines', ['ap_invoice_id' => $invoice->id, 'matching_status' => 'passed', 'price_variance_percent' => 1]);

        $payload['supplier_invoice_number'] = 'MATCH-FAIL';
        $payload['lines'][0]['unit_price'] = 110;
        $payload['lines'][0]['quantity'] = 1;
        $this->actingAs($finance)->post('/accounting/payables', $payload)->assertRedirect();
        $failedInvoice = DB::table('ap_invoices')->where('supplier_invoice_number', 'MATCH-FAIL')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/payables/'.$failedInvoice->id.'/post')->assertSessionHasErrors('matching');
        $this->assertDatabaseHas('ap_invoices', ['id' => $failedInvoice->id, 'status' => 'draft']);
    }

    public function test_credit_notes_and_settlement_reversals_preserve_ledger_history(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $expense = DB::table('gl_accounts')->where('code', '6100')->firstOrFail();
        $payable = DB::table('gl_accounts')->where('code', '2100')->firstOrFail();
        $cash = DB::table('gl_accounts')->where('code', '1100')->firstOrFail();
        $supplier = DB::table('suppliers')->where('is_active', true)->firstOrFail();
        $this->createAndPostApInvoice($finance, $supplier, $expense, $payable, 'REV-CN-01', 1000);
        $invoice = DB::table('ap_invoices')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/payments', ['invoice_id' => $invoice->id, 'payment_date' => today()->toDateString(), 'cash_account_id' => $cash->id, 'amount' => 400, 'payment_reference' => 'PAY-REV'])->assertRedirect();
        $payment = DB::table('ap_payments')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/bank-accounts', ['code' => 'REV-BANK', 'name' => 'Reversal Bank', 'bank_name' => 'Control Bank', 'currency' => 'IDR', 'gl_account_id' => $cash->id]);
        $bank = DB::table('accounting_bank_accounts')->firstOrFail();
        $csv = "date,description,amount,balance\n".today()->toDateString().",Supplier payment,-400,-400\n";
        $this->actingAs($finance)->post('/accounting/bank-statements/import', ['bank_account_id' => $bank->id, 'statement' => UploadedFile::fake()->createWithContent('payment.csv', $csv)])->assertRedirect();
        $bankLine = DB::table('bank_statement_lines')->firstOrFail();
        $this->assertSame('matched', $bankLine->status);
        $this->actingAs($finance)->post('/accounting/payments/'.$payment->id.'/reverse', ['reversal_date' => today()->toDateString(), 'reason' => 'Payment entered against wrong bank'])->assertSessionHasErrors('reversal');
        $this->actingAs($finance)->post('/accounting/bank-lines/'.$bankLine->id.'/unmatch')->assertRedirect();
        $this->actingAs($finance)->post('/accounting/payments/'.$payment->id.'/reverse', ['reversal_date' => today()->toDateString(), 'reason' => 'Payment entered against wrong bank'])->assertRedirect();
        $this->assertDatabaseHas('ap_payments', ['id' => $payment->id, 'status' => 'reversed']);
        $this->assertDatabaseHas('ap_invoices', ['id' => $invoice->id, 'paid_amount' => 0, 'outstanding_amount' => 1000]);
        $this->assertDatabaseHas('journal_entries', ['id' => $payment->journal_entry_id, 'reversed_by_id' => DB::table('ap_payments')->where('id', $payment->id)->value('reversal_journal_entry_id')]);

        $this->actingAs($finance)->post('/accounting/credit-notes', ['type' => 'ap', 'invoice_id' => $invoice->id, 'credit_date' => today()->toDateString(), 'amount' => 300, 'offset_account_id' => $expense->id, 'external_reference' => 'SUP-CN-01', 'reason' => 'Supplier granted service credit'])->assertRedirect();
        $note = DB::table('accounting_credit_notes')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/credit-notes/'.$note->id.'/post')->assertRedirect();
        $this->assertDatabaseHas('ap_invoices', ['id' => $invoice->id, 'credited_amount' => 300, 'outstanding_amount' => 700]);
        $this->assertDatabaseHas('accounting_credit_notes', ['id' => $note->id, 'status' => 'posted']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ap_payment_reversed']);
        $this->actingAs($finance)->get('/accounting/advanced-controls')->assertOk()->assertSee('SUP-CN-01')->assertSeeText('Credit Notes & Fiscal Close');
    }

    public function test_fiscal_year_close_and_reopen_use_balanced_reversal_journals(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $cash = DB::table('gl_accounts')->where('code', '1100')->firstOrFail();
        $revenue = DB::table('gl_accounts')->where('code', '4100')->firstOrFail();
        $expense = DB::table('gl_accounts')->where('code', '6100')->firstOrFail();
        $retained = DB::table('gl_accounts')->where('code', '3100')->firstOrFail();
        $year = now()->subYear()->year;
        $companyId = (int) $cash->company_id;
        $journalId = DB::table('journal_entries')->insertGetId(['company_id' => $companyId, 'branch_id' => null, 'document_number' => 'FY-'.$year, 'journal_date' => $year.'-06-30', 'source_type' => 'manual', 'status' => 'posted', 'is_adjustment' => false, 'memo' => 'Fiscal year activity', 'total_debit' => 1400, 'total_credit' => 1400, 'created_by' => $finance->id, 'posted_by' => $finance->id, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('journal_entry_lines')->insert([
            ['journal_entry_id' => $journalId, 'gl_account_id' => $cash->id, 'department_id' => null, 'description' => 'Cash revenue', 'debit' => 1000, 'credit' => 0, 'line_number' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $journalId, 'gl_account_id' => $revenue->id, 'department_id' => null, 'description' => 'Revenue', 'debit' => 0, 'credit' => 1000, 'line_number' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $journalId, 'gl_account_id' => $expense->id, 'department_id' => null, 'description' => 'Expense', 'debit' => 400, 'credit' => 0, 'line_number' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $journalId, 'gl_account_id' => $cash->id, 'department_id' => null, 'description' => 'Cash expense', 'debit' => 0, 'credit' => 400, 'line_number' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);
        foreach (range(1, 11) as $month) {
            $start = sprintf('%04d-%02d-01', $year, $month);
            DB::table('transaction_period_locks')->insert(['company_id' => $companyId, 'module' => 'accounting', 'starts_on' => $start, 'ends_on' => date('Y-m-t', strtotime($start)), 'reason' => 'Monthly close', 'locked_by' => $finance->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->actingAs($finance)->post('/accounting/fiscal-close', ['fiscal_year' => $year, 'retained_earnings_account_id' => $retained->id])->assertRedirect();
        $close = DB::table('fiscal_year_closes')->firstOrFail();
        $this->assertSame(600.0, (float) $close->net_income);
        $this->assertDatabaseHas('journal_entries', ['id' => $close->journal_entry_id, 'source_type' => 'fiscal_close', 'total_debit' => 1000, 'total_credit' => 1000]);
        $this->assertDatabaseHas('transaction_period_locks', ['company_id' => $companyId, 'starts_on' => $year.'-01-01', 'ends_on' => $year.'-12-31']);
        $this->actingAs($finance)->post('/accounting/fiscal-close/'.$close->id.'/reopen', ['reason' => 'Auditor requested year-end adjustment'])->assertRedirect();
        $this->assertDatabaseHas('fiscal_year_closes', ['id' => $close->id, 'status' => 'reopened']);
        $reopened = DB::table('fiscal_year_closes')->where('id', $close->id)->firstOrFail();
        $this->assertDatabaseHas('journal_entries', ['id' => $reopened->reversal_journal_entry_id, 'source_type' => 'fiscal_close_reversal', 'reversal_of_id' => $close->journal_entry_id]);
        $this->assertDatabaseMissing('transaction_period_locks', ['company_id' => $companyId, 'starts_on' => $year.'-01-01', 'ends_on' => $year.'-12-31']);
    }

    public function test_customer_receipt_reversal_restores_receivable_without_deleting_allocation(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $cash = DB::table('gl_accounts')->where('code', '1100')->firstOrFail();
        $receivable = DB::table('gl_accounts')->where('code', '1200')->firstOrFail();
        $revenue = DB::table('gl_accounts')->where('code', '4100')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/customers', ['code' => 'REV-CUST', 'name' => 'Reversal Customer', 'payment_terms_days' => 30]);
        $customer = DB::table('accounting_customers')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/receivables', ['customer_id' => $customer->id, 'invoice_date' => today()->toDateString(), 'due_date' => today()->addDays(30)->toDateString(), 'currency' => 'IDR', 'ar_account_id' => $receivable->id, 'lines' => [['gl_account_id' => $revenue->id, 'description' => 'Service', 'quantity' => 1, 'unit_price' => 500]]]);
        $invoice = DB::table('ar_invoices')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/receivables/'.$invoice->id.'/post');
        $this->actingAs($finance)->post('/accounting/receipts', ['invoice_id' => $invoice->id, 'receipt_date' => today()->toDateString(), 'cash_account_id' => $cash->id, 'amount' => 200, 'receipt_reference' => 'RCPT-REV'])->assertRedirect();
        $receipt = DB::table('ar_receipts')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/receipts/'.$receipt->id.'/reverse', ['reversal_date' => today()->toDateString(), 'reason' => 'Receipt assigned to incorrect customer'])->assertRedirect();
        $this->assertDatabaseHas('ar_receipts', ['id' => $receipt->id, 'status' => 'reversed']);
        $this->assertDatabaseHas('ar_invoices', ['id' => $invoice->id, 'received_amount' => 0, 'outstanding_amount' => 500, 'status' => 'posted']);
        $this->assertDatabaseCount('ar_receipt_allocations', 1);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ar_receipt_reversed', 'auditable_id' => $receipt->id]);
    }

    private function createAndPostApInvoice(User $finance, object $supplier, object $expense, object $payable, string $reference, float $amount): void
    {
        $this->actingAs($finance)->post('/accounting/payables', ['supplier_id' => $supplier->id, 'supplier_invoice_number' => $reference, 'invoice_date' => today()->toDateString(), 'due_date' => today()->addDays(30)->toDateString(), 'currency' => 'IDR', 'ap_account_id' => $payable->id, 'lines' => [['gl_account_id' => $expense->id, 'description' => 'Service', 'quantity' => 1, 'unit_price' => $amount]]])->assertRedirect();
        $invoice = DB::table('ap_invoices')->where('supplier_invoice_number', $reference)->firstOrFail();
        $this->actingAs($finance)->post('/accounting/payables/'.$invoice->id.'/post')->assertRedirect();
    }
}
