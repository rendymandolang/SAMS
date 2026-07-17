<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountsPayableTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_invoice_partial_and_final_payment_flow_to_general_ledger(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $supplier = DB::table('suppliers')->firstOrFail();
        $cash = DB::table('gl_accounts')->where('code', '1100')->firstOrFail();
        $payable = DB::table('gl_accounts')->where('code', '2100')->firstOrFail();
        $expense = DB::table('gl_accounts')->where('code', '6100')->firstOrFail();

        $response = $this->actingAs($finance)->post('/accounting/payables', [
            'supplier_id' => $supplier->id,
            'supplier_invoice_number' => 'SUP-INV-001',
            'invoice_date' => today()->toDateString(),
            'due_date' => today()->addDays(30)->toDateString(),
            'currency' => 'IDR',
            'ap_account_id' => $payable->id,
            'tax_amount' => 0,
            'lines' => [[
                'gl_account_id' => $expense->id,
                'description' => 'Operating supplies',
                'quantity' => 2,
                'unit_price' => 500000,
            ]],
        ]);

        $invoice = DB::table('ap_invoices')->firstOrFail();
        $response->assertRedirect('/accounting/payables/'.$invoice->id);
        $this->assertSame('draft', $invoice->status);
        $this->assertEquals(1000000, $invoice->total_amount);
        $this->actingAs($finance)->post('/accounting/payables/'.$invoice->id.'/post')->assertRedirect();

        $invoice = DB::table('ap_invoices')->where('id', $invoice->id)->firstOrFail();
        $this->assertSame('posted', $invoice->status);
        $invoiceJournal = DB::table('journal_entries')->where('id', $invoice->journal_entry_id)->firstOrFail();
        $this->assertEquals(1000000, $invoiceJournal->total_debit);
        $this->assertEquals(1000000, $invoiceJournal->total_credit);
        $this->assertSame('ap_invoice', $invoiceJournal->source_type);

        $this->actingAs($finance)->post('/accounting/payments', [
            'invoice_id' => $invoice->id,
            'payment_date' => today()->toDateString(),
            'cash_account_id' => $cash->id,
            'amount' => 400000,
            'payment_reference' => 'BANK-001',
        ])->assertRedirect();
        $this->assertDatabaseHas('ap_invoices', [
            'id' => $invoice->id,
            'status' => 'partially_paid',
            'paid_amount' => 400000,
            'outstanding_amount' => 600000,
        ]);

        $this->actingAs($finance)->post('/accounting/payments', [
            'invoice_id' => $invoice->id,
            'payment_date' => today()->toDateString(),
            'cash_account_id' => $cash->id,
            'amount' => 600000,
            'payment_reference' => 'BANK-002',
        ])->assertRedirect();
        $this->assertDatabaseHas('ap_invoices', [
            'id' => $invoice->id,
            'status' => 'paid',
            'paid_amount' => 1000000,
            'outstanding_amount' => 0,
        ]);
        $this->assertDatabaseCount('ap_payments', 2);
        $this->assertDatabaseCount('ap_payment_allocations', 2);
        $this->assertDatabaseCount('journal_entries', 3);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ap_invoice_posted', 'auditable_id' => $invoice->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ap_payment_posted']);
        $this->actingAs($finance)->get('/accounting/payables')->assertOk()->assertSee('SUP-INV-001')->assertSee('Paid');
        $this->actingAs($finance)->get('/accounting/payables/'.$invoice->id.'/print')->assertOk()->assertSee('SUPPLIER INVOICE')->assertSee('Prepared By');
    }

    public function test_duplicate_supplier_invoice_and_overpayment_are_rejected(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $supplier = DB::table('suppliers')->firstOrFail();
        $cash = DB::table('gl_accounts')->where('code', '1100')->firstOrFail();
        $payable = DB::table('gl_accounts')->where('code', '2100')->firstOrFail();
        $expense = DB::table('gl_accounts')->where('code', '6100')->firstOrFail();
        $payload = [
            'supplier_id' => $supplier->id,
            'supplier_invoice_number' => 'DUP-001',
            'invoice_date' => today()->toDateString(),
            'due_date' => today()->addDays(10)->toDateString(),
            'currency' => 'IDR',
            'ap_account_id' => $payable->id,
            'tax_amount' => 0,
            'lines' => [['gl_account_id' => $expense->id, 'description' => 'Service', 'quantity' => 1, 'unit_price' => 100000]],
        ];

        $this->actingAs($finance)->post('/accounting/payables', $payload)->assertRedirect();
        $invoice = DB::table('ap_invoices')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/payables', $payload)->assertSessionHasErrors('supplier_invoice_number');
        $this->actingAs($finance)->post('/accounting/payables/'.$invoice->id.'/post')->assertRedirect();
        $this->actingAs($finance)->post('/accounting/payments', [
            'invoice_id' => $invoice->id,
            'payment_date' => today()->toDateString(),
            'cash_account_id' => $cash->id,
            'amount' => 100001,
        ])->assertSessionHasErrors('allocations');

        $this->assertDatabaseCount('ap_invoices', 1);
        $this->assertDatabaseCount('ap_payments', 0);
    }
}
