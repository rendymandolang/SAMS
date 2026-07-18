<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountingCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_supplier_invoice_uses_company_rate_and_posts_base_and_foreign_ledger_values(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $supplier = DB::table('suppliers')->where('is_active', true)->firstOrFail();
        $expense = DB::table('gl_accounts')->where('code', '6100')->firstOrFail();
        $payable = DB::table('gl_accounts')->where('code', '2100')->firstOrFail();
        $date = today()->toDateString();
        $this->actingAs($finance)->post('/accounting/configuration/exchange-rates', ['currency' => 'USD', 'rate_date' => $date, 'rate_to_base' => 16000, 'source' => 'Test rate'])->assertRedirect();
        $this->actingAs($finance)->post('/accounting/payables', ['supplier_id' => $supplier->id, 'supplier_invoice_number' => 'FX-USD-01', 'invoice_date' => $date, 'due_date' => today()->addDays(30)->toDateString(), 'currency' => 'USD', 'ap_account_id' => $payable->id, 'lines' => [['gl_account_id' => $expense->id, 'description' => 'Imported service', 'quantity' => 2, 'unit_price' => 100]]])->assertRedirect();
        $invoice = DB::table('ap_invoices')->where('supplier_invoice_number', 'FX-USD-01')->firstOrFail();
        $this->assertSame(200.0, (float) $invoice->foreign_total_amount);
        $this->assertSame(3200000.0, (float) $invoice->total_amount);
        $this->actingAs($finance)->post('/accounting/payables/'.$invoice->id.'/post')->assertRedirect();
        $invoice = DB::table('ap_invoices')->find($invoice->id);
        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $invoice->journal_entry_id, 'gl_account_id' => $payable->id, 'credit' => 3200000, 'foreign_currency' => 'USD', 'foreign_credit' => 200]);
    }

    public function test_missing_foreign_rate_blocks_invoice_and_base_currency_still_uses_rate_one(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $supplier = DB::table('suppliers')->where('is_active', true)->firstOrFail();
        $expense = DB::table('gl_accounts')->where('code', '6100')->firstOrFail();
        $payable = DB::table('gl_accounts')->where('code', '2100')->firstOrFail();
        $payload = ['supplier_id' => $supplier->id, 'supplier_invoice_number' => 'FX-NO-RATE', 'invoice_date' => today()->toDateString(), 'due_date' => today()->addDay()->toDateString(), 'currency' => 'EUR', 'ap_account_id' => $payable->id, 'lines' => [['gl_account_id' => $expense->id, 'description' => 'No rate', 'quantity' => 1, 'unit_price' => 10]]];
        $this->actingAs($finance)->post('/accounting/payables', $payload)->assertSessionHasErrors('currency');
        $this->assertDatabaseMissing('ap_invoices', ['supplier_invoice_number' => 'FX-NO-RATE']);
    }

    public function test_foreign_supplier_settlement_posts_realized_exchange_loss(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $supplier = DB::table('suppliers')->where('is_active', true)->firstOrFail();
        $expense = DB::table('gl_accounts')->where('code', '6100')->firstOrFail();
        $payable = DB::table('gl_accounts')->where('code', '2100')->firstOrFail();
        $cash = DB::table('gl_accounts')->where('code', '1100')->firstOrFail();
        $gain = DB::table('gl_accounts')->where('type', 'revenue')->where('allow_posting', true)->firstOrFail();
        $loss = DB::table('gl_accounts')->where('type', 'expense')->where('allow_posting', true)->firstOrFail();
        $invoiceDate = today()->toDateString();
        $paymentDate = today()->addDay()->toDateString();
        $this->actingAs($finance)->post('/accounting/configuration/exchange-rates', ['currency' => 'USD', 'rate_date' => $invoiceDate, 'rate_to_base' => 16000]);
        $this->actingAs($finance)->post('/accounting/configuration/exchange-rates', ['currency' => 'USD', 'rate_date' => $paymentDate, 'rate_to_base' => 17000]);
        $this->actingAs($finance)->post('/accounting/configuration/fx-accounts', ['realized_fx_gain_account_id' => $gain->id, 'realized_fx_loss_account_id' => $loss->id, 'unrealized_fx_gain_account_id' => $gain->id, 'unrealized_fx_loss_account_id' => $loss->id])->assertRedirect();
        $this->actingAs($finance)->post('/accounting/payables', ['supplier_id' => $supplier->id, 'supplier_invoice_number' => 'FX-SETTLE', 'invoice_date' => $invoiceDate, 'due_date' => today()->addDays(30)->toDateString(), 'currency' => 'USD', 'ap_account_id' => $payable->id, 'lines' => [['gl_account_id' => $expense->id, 'description' => 'Foreign service', 'quantity' => 1, 'unit_price' => 200]]])->assertSessionHasNoErrors();
        $invoice = DB::table('ap_invoices')->where('supplier_invoice_number', 'FX-SETTLE')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/payables/'.$invoice->id.'/post')->assertSessionHasNoErrors();
        $this->actingAs($finance)->post('/accounting/payments', ['invoice_id' => $invoice->id, 'payment_date' => $paymentDate, 'cash_account_id' => $cash->id, 'amount' => 200])->assertSessionHasNoErrors()->assertRedirect();
        $payment = DB::table('ap_payments')->firstOrFail();
        $this->assertSame(3400000.0, (float) $payment->amount);
        $this->assertSame(200000.0, (float) $payment->realized_fx_amount);
        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $payment->journal_entry_id, 'gl_account_id' => $loss->id, 'debit' => 200000]);
        $this->assertDatabaseHas('ap_invoices', ['id' => $invoice->id, 'status' => 'paid', 'foreign_outstanding_amount' => 0]);
    }

    public function test_period_end_revaluation_updates_open_payable_and_posts_unrealized_loss_once(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $supplier = DB::table('suppliers')->where('is_active', true)->firstOrFail();
        $expense = DB::table('gl_accounts')->where('code', '6100')->firstOrFail();
        $payable = DB::table('gl_accounts')->where('code', '2100')->firstOrFail();
        $gain = DB::table('gl_accounts')->where('type', 'revenue')->where('allow_posting', true)->firstOrFail();
        $loss = DB::table('gl_accounts')->where('type', 'expense')->where('allow_posting', true)->firstOrFail();
        $invoiceDate = today()->toDateString();
        $revaluationDate = today()->addDay()->toDateString();
        $this->actingAs($finance)->post('/accounting/configuration/exchange-rates', ['currency' => 'USD', 'rate_date' => $invoiceDate, 'rate_to_base' => 16000]);
        $this->actingAs($finance)->post('/accounting/configuration/fx-accounts', ['realized_fx_gain_account_id' => $gain->id, 'realized_fx_loss_account_id' => $loss->id, 'unrealized_fx_gain_account_id' => $gain->id, 'unrealized_fx_loss_account_id' => $loss->id]);
        $this->actingAs($finance)->post('/accounting/payables', ['supplier_id' => $supplier->id, 'supplier_invoice_number' => 'FX-REVALUE', 'invoice_date' => $invoiceDate, 'due_date' => today()->addDays(30)->toDateString(), 'currency' => 'USD', 'ap_account_id' => $payable->id, 'lines' => [['gl_account_id' => $expense->id, 'description' => 'Foreign payable', 'quantity' => 1, 'unit_price' => 200]]]);
        $invoice = DB::table('ap_invoices')->where('supplier_invoice_number', 'FX-REVALUE')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/payables/'.$invoice->id.'/post');
        $this->actingAs($finance)->post('/accounting/configuration/exchange-rates', ['currency' => 'USD', 'rate_date' => $revaluationDate, 'rate_to_base' => 17000]);
        $this->actingAs($finance)->post('/accounting/configuration/fx-revaluation', ['currency' => 'USD', 'revaluation_date' => $revaluationDate])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ap_invoices', ['id' => $invoice->id, 'carrying_amount' => 3400000]);
        $run = DB::table('accounting_fx_revaluations')->firstOrFail();
        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $run->journal_entry_id, 'gl_account_id' => $loss->id, 'debit' => 200000]);
        $this->actingAs($finance)->post('/accounting/configuration/fx-revaluation', ['currency' => 'USD', 'revaluation_date' => $revaluationDate])->assertStatus(422);
    }
}
