<?php
namespace Tests\Feature;
use App\Models\User;use Illuminate\Foundation\Testing\RefreshDatabase;use Illuminate\Support\Facades\DB;use Tests\TestCase;
class AccountingReportTest extends TestCase{use RefreshDatabase;
 public function test_posted_journal_flows_to_all_core_financial_reports():void{$this->seed();$finance=User::where('email','finance@sams.local')->firstOrFail();$this->actingAs($finance)->get('/accounting');$cash=DB::table('gl_accounts')->where('code','1100')->firstOrFail();$expense=DB::table('gl_accounts')->where('code','6100')->firstOrFail();$this->actingAs($finance)->post('/accounting/journals',['journal_date'=>today()->toDateString(),'memo'=>'Report flow test','lines'=>[['gl_account_id'=>$expense->id,'description'=>'Expense','debit'=>250000,'credit'=>0],['gl_account_id'=>$cash->id,'description'=>'Cash','debit'=>0,'credit'=>250000]]]);$entry=DB::table('journal_entries')->firstOrFail();$this->actingAs($finance)->post('/accounting/journals/'.$entry->id.'/post');
  $this->actingAs($finance)->get('/accounting/reports/general-ledger')->assertOk()->assertSee('General Ledger')->assertSee($entry->document_number);
  $this->actingAs($finance)->get('/accounting/reports/trial-balance')->assertOk()->assertSee('Trial Balance')->assertSee('250.000,00');
  $this->actingAs($finance)->get('/accounting/reports/profit-loss')->assertOk()->assertSee('Profit Loss')->assertSee('NET PROFIT / (LOSS)');
  $this->actingAs($finance)->get('/accounting/reports/balance-sheet?print=1')->assertOk()->assertSee('Balance Sheet')->assertSee('Current Earnings');
 }
 public function test_draft_journal_is_excluded_from_reports():void{$this->seed();$admin=User::where('email','admin@sams.local')->firstOrFail();$this->actingAs($admin)->get('/accounting');$cash=DB::table('gl_accounts')->where('code','1100')->firstOrFail();$expense=DB::table('gl_accounts')->where('code','6100')->firstOrFail();$this->actingAs($admin)->post('/accounting/journals',['journal_date'=>today()->toDateString(),'memo'=>'Draft must stay hidden','lines'=>[['gl_account_id'=>$expense->id,'debit'=>10,'credit'=>0],['gl_account_id'=>$cash->id,'debit'=>0,'credit'=>10]]]);$this->actingAs($admin)->get('/accounting/reports/general-ledger')->assertOk()->assertDontSee('Draft must stay hidden');}
}
