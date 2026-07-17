<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_statement_import_manual_matching_and_locked_completion(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $cash = DB::table('gl_accounts')->where('code', '1100')->firstOrFail();
        $revenue = DB::table('gl_accounts')->where('code', '4100')->firstOrFail();
        $date = today()->toDateString();

        $this->actingAs($finance)->post('/accounting/bank-accounts', [
            'code' => 'BRI-01', 'name' => 'BRI Operating', 'bank_name' => 'Bank BRI',
            'account_number_masked' => '**** 7788', 'currency' => 'IDR', 'gl_account_id' => $cash->id,
        ])->assertRedirect();
        $bank = DB::table('accounting_bank_accounts')->firstOrFail();
        $firstLine = $this->postedBankJournal((int) $cash->company_id, (int) $finance->id, (int) $cash->id, (int) $revenue->id, 'BANK-001', $date, 1000);
        $secondLine = $this->postedBankJournal((int) $cash->company_id, (int) $finance->id, (int) $cash->id, (int) $revenue->id, 'BANK-002', $date, 1000);
        $csv = "Tanggal,Keterangan,Referensi,Debet,Kredit,Saldo\n".
            today()->format('d/m/Y').",Customer receipt one,TRX-001,0,1000,1000\n".
            today()->format('d/m/Y').",Customer receipt two,TRX-002,0,1000,2000\n";

        $this->actingAs($finance)->post('/accounting/bank-statements/import', [
            'bank_account_id' => $bank->id, 'statement' => UploadedFile::fake()->createWithContent('bri-july.csv', $csv),
        ])->assertRedirect();
        $reconciliation = DB::table('bank_reconciliations')->firstOrFail();
        $lines = DB::table('bank_statement_lines')->orderBy('id')->get();
        $this->assertCount(2, $lines);
        $this->assertSame(0, $lines->where('status', 'matched')->count(), 'Ambiguous transactions must not be auto-matched.');
        $this->assertSame(0.0, (float) $reconciliation->difference);

        $this->actingAs($finance)->post('/accounting/bank-lines/'.$lines[0]->id.'/match', ['journal_entry_line_id' => $firstLine])->assertRedirect();
        $this->actingAs($finance)->post('/accounting/bank-lines/'.$lines[1]->id.'/match', ['journal_entry_line_id' => $secondLine])->assertRedirect();
        $this->actingAs($finance)->post('/accounting/bank-reconciliation/'.$reconciliation->id.'/complete')->assertRedirect();
        $this->assertDatabaseHas('bank_reconciliations', ['id' => $reconciliation->id, 'status' => 'completed', 'difference' => 0]);
        $this->assertDatabaseHas('bank_statement_imports', ['status' => 'reconciled', 'line_count' => 2]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'bank_reconciliation_completed', 'auditable_id' => $reconciliation->id]);
        $this->actingAs($finance)->get('/accounting/bank-reconciliation/'.$reconciliation->id)->assertOk()->assertSee('BRI Operating')->assertSee('Completed');
        $this->actingAs($finance)->get('/accounting/bank-reconciliation/'.$reconciliation->id.'/print')->assertOk()->assertSee('BANK RECONCILIATION')->assertSee('BANK-001');
        $this->actingAs($finance)->get('/accounting/bank-reconciliation/template/csv')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($finance)->post('/accounting/bank-lines/'.$lines[0]->id.'/unmatch')->assertStatus(422);

        $this->actingAs($finance)->post('/accounting/bank-statements/import', [
            'bank_account_id' => $bank->id, 'statement' => UploadedFile::fake()->createWithContent('same-content.csv', $csv),
        ])->assertSessionHasErrors('statement');
        $this->assertDatabaseCount('bank_statement_imports', 1);
    }

    public function test_unresolved_line_requires_audited_resolution_before_completion(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $cash = DB::table('gl_accounts')->where('code', '1100')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/bank-accounts', [
            'code' => 'CASH-01', 'name' => 'Settlement Account', 'bank_name' => 'Test Bank',
            'currency' => 'IDR', 'gl_account_id' => $cash->id,
        ]);
        $bank = DB::table('accounting_bank_accounts')->firstOrFail();
        $csv = "date,description,amount,balance\n".today()->toDateString().",Duplicate bank row,100,0\n";
        $this->actingAs($finance)->post('/accounting/bank-statements/import', [
            'bank_account_id' => $bank->id, 'statement' => UploadedFile::fake()->createWithContent('exception.csv', $csv),
        ])->assertRedirect();
        $reconciliation = DB::table('bank_reconciliations')->firstOrFail();
        $line = DB::table('bank_statement_lines')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/bank-reconciliation/'.$reconciliation->id.'/complete')->assertSessionHasErrors('reconciliation');
        $this->actingAs($finance)->post('/accounting/bank-lines/'.$line->id.'/exclude', ['reason' => 'Duplicate row confirmed against bank source'])->assertRedirect();
        $this->actingAs($finance)->post('/accounting/bank-reconciliation/'.$reconciliation->id.'/complete')->assertRedirect();
        $this->assertDatabaseHas('bank_statement_lines', ['id' => $line->id, 'status' => 'excluded', 'resolution_note' => 'Duplicate row confirmed against bank source']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'bank_statement_line_excluded', 'auditable_id' => $line->id]);
    }

    public function test_localized_semicolon_statement_is_auto_matched_safely(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $cash = DB::table('gl_accounts')->where('code', '1100')->firstOrFail();
        $revenue = DB::table('gl_accounts')->where('code', '4100')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/bank-accounts', [
            'code' => 'LOCAL-01', 'name' => 'Localized CSV', 'bank_name' => 'Local Bank', 'currency' => 'IDR', 'gl_account_id' => $cash->id,
        ]);
        $bank = DB::table('accounting_bank_accounts')->firstOrFail();
        $journalLine = $this->postedBankJournal((int) $cash->company_id, (int) $finance->id, (int) $cash->id, (int) $revenue->id, 'LOCAL-001', today()->toDateString(), 1500.50);
        $csv = "Tanggal;Keterangan;Referensi;Nominal;Saldo\n".today()->format('d/m/Y').";Transfer lokal;REF-LOCAL;1.500,50;1.500,50\n";
        $this->actingAs($finance)->post('/accounting/bank-statements/import', [
            'bank_account_id' => $bank->id, 'statement' => UploadedFile::fake()->createWithContent('localized.csv', $csv),
        ])->assertRedirect();
        $this->assertDatabaseHas('bank_statement_lines', ['status' => 'matched', 'matched_journal_entry_line_id' => $journalLine, 'amount' => 1500.5]);
        $reconciliation = DB::table('bank_reconciliations')->firstOrFail();
        $this->actingAs($finance)->post('/accounting/bank-reconciliation/'.$reconciliation->id.'/complete')->assertRedirect();
        $this->assertDatabaseHas('bank_reconciliations', ['id' => $reconciliation->id, 'status' => 'completed']);
    }

    private function postedBankJournal(int $companyId, int $userId, int $bankAccountId, int $offsetAccountId, string $document, string $date, float $amount): int
    {
        $journalId = DB::table('journal_entries')->insertGetId([
            'company_id' => $companyId, 'branch_id' => null, 'document_number' => $document, 'journal_date' => $date,
            'source_type' => 'manual', 'status' => 'posted', 'is_adjustment' => false, 'memo' => 'Bank test transaction',
            'total_debit' => $amount, 'total_credit' => $amount, 'created_by' => $userId, 'posted_by' => $userId,
            'posted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $bankLineId = DB::table('journal_entry_lines')->insertGetId([
            'journal_entry_id' => $journalId, 'gl_account_id' => $bankAccountId, 'department_id' => null,
            'description' => 'Bank receipt', 'debit' => $amount, 'credit' => 0, 'line_number' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('journal_entry_lines')->insert([
            'journal_entry_id' => $journalId, 'gl_account_id' => $offsetAccountId, 'department_id' => null,
            'description' => 'Offset', 'debit' => 0, 'credit' => $amount, 'line_number' => 2, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $bankLineId;
    }
}
