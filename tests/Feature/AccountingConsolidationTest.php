<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountingConsolidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_can_generate_eliminate_and_finalize_balanced_consolidation(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $cash = DB::table('gl_accounts')->where('code', '1100')->firstOrFail();
        $revenue = DB::table('gl_accounts')->where('type', 'revenue')->where('allow_posting', true)->firstOrFail();
        $date = today()->toDateString();
        $this->actingAs($finance)->post('/accounting/journals', ['journal_date' => $date, 'memo' => 'Consolidation source', 'lines' => [['gl_account_id' => $cash->id, 'description' => 'Cash', 'debit' => 1000, 'credit' => 0], ['gl_account_id' => $revenue->id, 'description' => 'Revenue', 'debit' => 0, 'credit' => 1000]]]);
        $journal = DB::table('journal_entries')->where('memo', 'Consolidation source')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/journals/'.$journal->id.'/post');

        $this->actingAs($finance)->post('/accounting/consolidation', ['name' => 'SuperSoft Group', 'presentation_currency' => 'IDR'])->assertRedirect();
        $group = DB::table('accounting_consolidation_groups')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/consolidation/'.$group->id.'/runs', ['period_from' => today()->startOfYear()->toDateString(), 'period_to' => $date])->assertRedirect();
        $run = DB::table('accounting_consolidation_runs')->firstOrFail();
        $this->assertSame(1000.0, (float) $run->total_debit);
        $this->assertSame(1000.0, (float) $run->total_credit);
        $this->assertDatabaseHas('accounting_consolidation_lines', ['run_id' => $run->id, 'consolidation_code' => $cash->code, 'debit' => 1000]);

        $elimination = ['lines' => [['code' => 'IC-AR', 'name' => 'Intercompany Receivable', 'type' => 'asset', 'description' => 'Eliminate IC', 'debit' => 0, 'credit' => 250], ['code' => 'IC-AP', 'name' => 'Intercompany Payable', 'type' => 'liability', 'description' => 'Eliminate IC', 'debit' => 250, 'credit' => 0]]];
        $this->actingAs($finance)->post('/accounting/consolidation/'.$group->id.'/runs/'.$run->id.'/eliminations', $elimination)->assertRedirect();
        $this->actingAs($finance)->post('/accounting/consolidation/'.$group->id.'/runs/'.$run->id.'/finalize')->assertRedirect();
        $this->assertDatabaseHas('accounting_consolidation_runs', ['id' => $run->id, 'status' => 'completed', 'total_debit' => 1250, 'total_credit' => 1250]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'accounting_consolidation_finalized', 'auditable_id' => $run->id]);
        $this->actingAs($finance)->get('/accounting/consolidation/'.$group->id.'/runs/'.$run->id)->assertOk()->assertSee('Consolidated Trial Balance')->assertSee('Intercompany Receivable');
    }

    public function test_unbalanced_elimination_is_rejected_and_inaccessible_company_cannot_be_added(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/consolidation', ['name' => 'Control Group', 'presentation_currency' => 'IDR']);
        $group = DB::table('accounting_consolidation_groups')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/consolidation/'.$group->id.'/runs', ['period_from' => today()->startOfYear()->toDateString(), 'period_to' => today()->toDateString()]);
        $run = DB::table('accounting_consolidation_runs')->firstOrFail();
        $payload = ['lines' => [['code' => 'IC1', 'name' => 'IC debit', 'type' => 'asset', 'debit' => 100], ['code' => 'IC2', 'name' => 'IC credit', 'type' => 'liability', 'credit' => 90]]];
        $this->actingAs($finance)->post('/accounting/consolidation/'.$group->id.'/runs/'.$run->id.'/eliminations', $payload)->assertSessionHasErrors('lines');

        $otherCompany = DB::table('companies')->insertGetId(['public_id' => fake()->uuid(), 'code' => 'NOACCESS', 'name' => 'No Access Entity', 'legal_name' => 'No Access Entity', 'timezone' => 'Asia/Makassar', 'currency' => 'IDR', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($finance)->post('/accounting/consolidation/'.$group->id.'/members', ['company_id' => $otherCompany, 'ownership_percent' => 100])->assertForbidden();
    }
}
