<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountsReceivableTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_invoice_and_partial_receipts_flow_to_general_ledger(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $cash = DB::table('gl_accounts')->where('code', '1100')->firstOrFail();
        $receivable = DB::table('gl_accounts')->where('code', '1200')->firstOrFail();
        $revenue = DB::table('gl_accounts')->where('code', '4100')->firstOrFail();

        $this->actingAs($finance)->post('/accounting/customers', [
            'code' => 'CORP-01', 'name' => 'Corporate Guest Test', 'email' => 'finance@guest.test',
            'payment_terms_days' => 30,
        ])->assertRedirect();
        $customer = DB::table('accounting_customers')->firstOrFail();

        $this->actingAs($finance)->post('/accounting/receivables', [
            'customer_id' => $customer->id, 'invoice_date' => today()->toDateString(),
            'due_date' => today()->addDays(30)->toDateString(), 'currency' => 'IDR',
            'ar_account_id' => $receivable->id, 'tax_amount' => 0,
            'lines' => [['gl_account_id' => $revenue->id, 'description' => 'Hospitality service', 'quantity' => 2, 'unit_price' => 750000]],
        ])->assertRedirect();
        $invoice = DB::table('ar_invoices')->firstOrFail();
        $this->assertSame('draft', $invoice->status);
        $this->actingAs($finance)->post('/accounting/receivables/'.$invoice->id.'/post')->assertRedirect();
        $invoice = DB::table('ar_invoices')->where('id', $invoice->id)->firstOrFail();
        $this->assertSame('posted', $invoice->status);
        $this->assertDatabaseHas('journal_entries', ['id' => $invoice->journal_entry_id, 'source_type' => 'ar_invoice', 'total_debit' => 1500000, 'total_credit' => 1500000]);

        $this->actingAs($finance)->post('/accounting/receipts', ['invoice_id' => $invoice->id, 'receipt_date' => today()->toDateString(), 'cash_account_id' => $cash->id, 'amount' => 500000, 'receipt_reference' => 'RCPT-1'])->assertRedirect();
        $this->assertDatabaseHas('ar_invoices', ['id' => $invoice->id, 'status' => 'partially_received', 'received_amount' => 500000, 'outstanding_amount' => 1000000]);
        $this->actingAs($finance)->post('/accounting/receipts', ['invoice_id' => $invoice->id, 'receipt_date' => today()->toDateString(), 'cash_account_id' => $cash->id, 'amount' => 1000000, 'receipt_reference' => 'RCPT-2'])->assertRedirect();
        $this->assertDatabaseHas('ar_invoices', ['id' => $invoice->id, 'status' => 'received', 'received_amount' => 1500000, 'outstanding_amount' => 0]);
        $this->assertDatabaseCount('ar_receipts', 2);
        $this->assertDatabaseCount('ar_receipt_allocations', 2);
        $this->assertDatabaseCount('journal_entries', 3);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ar_invoice_posted', 'auditable_id' => $invoice->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ar_receipt_posted']);
        $this->actingAs($finance)->get('/accounting/receivables')->assertOk()->assertSee('Corporate Guest Test')->assertSee('Received');
        $this->actingAs($finance)->get('/accounting/receivables/'.$invoice->id.'/print')->assertOk()->assertSee('CUSTOMER INVOICE')->assertSee('Prepared By');
    }

    public function test_duplicate_customer_code_and_over_receipt_are_rejected(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $cash = DB::table('gl_accounts')->where('code', '1100')->firstOrFail();
        $receivable = DB::table('gl_accounts')->where('code', '1200')->firstOrFail();
        $revenue = DB::table('gl_accounts')->where('code', '4100')->firstOrFail();
        $customerPayload = ['code' => 'C-01', 'name' => 'Customer One', 'payment_terms_days' => 14];
        $this->actingAs($finance)->post('/accounting/customers', $customerPayload)->assertRedirect();
        $this->actingAs($finance)->post('/accounting/customers', $customerPayload)->assertSessionHasErrors('code');
        $customer = DB::table('accounting_customers')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/receivables', [
            'customer_id' => $customer->id, 'invoice_date' => today()->toDateString(), 'due_date' => today()->addDays(14)->toDateString(),
            'currency' => 'IDR', 'ar_account_id' => $receivable->id, 'tax_amount' => 0,
            'lines' => [['gl_account_id' => $revenue->id, 'description' => 'Service', 'quantity' => 1, 'unit_price' => 100000]],
        ]);
        $invoice = DB::table('ar_invoices')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/receivables/'.$invoice->id.'/post');
        $this->actingAs($finance)->post('/accounting/receipts', ['invoice_id' => $invoice->id, 'receipt_date' => today()->toDateString(), 'cash_account_id' => $cash->id, 'amount' => 100001])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('ar_receipts', 0);
    }
}
