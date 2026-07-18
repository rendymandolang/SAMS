<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountingAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_can_map_cash_flow_accounts_and_generate_balanced_recurring_draft(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $cash = DB::table('gl_accounts')->where('code', '1100')->firstOrFail();
        $expense = DB::table('gl_accounts')->where('code', '6100')->firstOrFail();

        $this->actingAs($finance)->post('/accounting/automation/accounts/'.$cash->id, ['is_cash_account' => 1, 'cash_flow_activity' => 'operating'])->assertRedirect();
        $this->assertDatabaseHas('gl_accounts', ['id' => $cash->id, 'is_cash_account' => true]);

        $this->actingAs($finance)->post('/accounting/automation/templates', [
            'name' => 'Monthly office accrual', 'frequency' => 'monthly', 'starts_on' => today()->startOfMonth()->toDateString(),
            'memo' => 'Monthly office expense accrual',
            'lines' => [
                ['gl_account_id' => $expense->id, 'description' => 'Office expense', 'debit' => 1000, 'credit' => 0],
                ['gl_account_id' => $cash->id, 'description' => 'Cash provision', 'debit' => 0, 'credit' => 1000],
            ],
        ])->assertRedirect();
        $template = DB::table('accounting_recurring_templates')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/automation/templates/'.$template->id.'/generate', ['journal_date' => $template->next_run_on])->assertRedirect();
        $journal = DB::table('journal_entries')->where('source_type', 'recurring')->firstOrFail();
        $this->assertSame('draft', $journal->status);
        $this->assertEquals((float) $journal->total_debit, (float) $journal->total_credit);
        $this->assertDatabaseHas('accounting_recurring_runs', ['template_id' => $template->id, 'journal_entry_id' => $journal->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'accounting_recurring_journal_generated', 'auditable_id' => $journal->id]);
    }

    public function test_cash_flow_journal_register_and_department_profit_loss_are_available(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $cash = DB::table('gl_accounts')->where('code', '1100')->firstOrFail();
        DB::table('gl_accounts')->where('id', $cash->id)->update(['is_cash_account' => true]);

        $this->actingAs($finance)->get('/accounting/reports/cash-flow')->assertOk()->assertSee('Closing cash balance');
        $this->actingAs($finance)->get('/accounting/reports/journal-register')->assertOk()->assertSee('Journal Register');
        $this->actingAs($finance)->get('/accounting/reports/department-profit-loss')->assertOk()->assertSee('Department Profit Loss');
        $this->actingAs($finance)->get('/accounting/automation')->assertOk()->assertSee('Recurring journals');
    }

    public function test_unbalanced_recurring_template_is_rejected(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $cash = DB::table('gl_accounts')->where('code', '1100')->firstOrFail();
        $expense = DB::table('gl_accounts')->where('code', '6100')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/automation/templates', ['name' => 'Invalid', 'frequency' => 'monthly', 'starts_on' => today()->toDateString(), 'memo' => 'Invalid', 'lines' => [['gl_account_id' => $expense->id, 'debit' => 100], ['gl_account_id' => $cash->id, 'credit' => 90]]])->assertSessionHasErrors('lines');
        $this->assertDatabaseCount('accounting_recurring_templates', 0);
    }
}
