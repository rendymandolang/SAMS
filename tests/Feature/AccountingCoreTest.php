<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\FreshInstallationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountingCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_installation_does_not_create_a_coa_template(): void
    {
        $this->seed(FreshInstallationSeeder::class);
        $admin = User::where('email', 'admin@supersoft.local')->firstOrFail();

        $this->actingAs($admin)
            ->get('/accounting')
            ->assertOk()
            ->assertSee('COA masih kosong');

        $this->assertDatabaseCount('gl_accounts', 0);
    }

    public function test_finance_can_create_a_company_account(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();

        $this->actingAs($finance)
            ->post('/accounting/accounts', [
                'code' => '1200-01',
                'name' => 'Trade Accounts Receivable',
                'type' => 'asset',
                'normal_balance' => 'debit',
                'allow_posting' => true,
            ])
            ->assertRedirect('/accounting');

        $this->assertDatabaseHas('gl_accounts', [
            'code' => '1200-01',
            'name' => 'Trade Accounts Receivable',
            'type' => 'asset',
            'allow_posting' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'gl_account_created',
            'auditable_type' => 'gl_account',
        ]);
    }

    public function test_similar_account_name_requires_explicit_confirmation(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $payload = [
            'code' => '1110',
            'name' => 'Cash and Bank',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'allow_posting' => true,
        ];

        $this->actingAs($finance)
            ->from('/accounting')
            ->post('/accounting/accounts', $payload)
            ->assertRedirect('/accounting')
            ->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('gl_accounts', ['code' => '1110']);

        $this->actingAs($finance)
            ->post('/accounting/accounts', $payload + ['confirm_similar' => true])
            ->assertRedirect('/accounting');

        $this->assertDatabaseHas('gl_accounts', ['code' => '1110']);
    }

    public function test_finance_can_create_balance_post_and_print_journal(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $cash = DB::table('gl_accounts')->where('code', '1100')->firstOrFail();
        $expense = DB::table('gl_accounts')->where('code', '6100')->firstOrFail();

        $response = $this->actingAs($finance)->post('/accounting/journals', [
            'journal_date' => today()->toDateString(),
            'memo' => 'Biaya operasional test',
            'lines' => [
                ['gl_account_id' => $expense->id, 'description' => 'Expense', 'debit' => 100000, 'credit' => 0],
                ['gl_account_id' => $cash->id, 'description' => 'Cash', 'debit' => 0, 'credit' => 100000],
            ],
        ]);

        $entry = DB::table('journal_entries')->firstOrFail();
        $response->assertRedirect('/accounting/journals/'.$entry->id);
        $this->actingAs($finance)->post('/accounting/journals/'.$entry->id.'/post')->assertRedirect();

        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->id,
            'status' => 'posted',
            'total_debit' => 100000,
            'total_credit' => 100000,
        ]);
        $this->actingAs($finance)
            ->get('/accounting/journals/'.$entry->id.'/print')
            ->assertOk()
            ->assertSee('JOURNAL VOUCHER')
            ->assertSee('Prepared by');
        $this->assertDatabaseHas('audit_logs', ['event' => 'journal_posted', 'auditable_id' => $entry->id]);
    }

    public function test_unbalanced_journal_is_rejected_and_staff_has_no_access(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@sams.local')->firstOrFail();
        $staff = User::where('email', 'staff@sams.local')->firstOrFail();
        $accounts = DB::table('gl_accounts')->where('allow_posting', true)->limit(2)->get();

        $this->actingAs($admin)->post('/accounting/journals', [
            'journal_date' => today()->toDateString(),
            'memo' => 'Unbalanced',
            'lines' => [
                ['gl_account_id' => $accounts[0]->id, 'debit' => 100, 'credit' => 0],
                ['gl_account_id' => $accounts[1]->id, 'debit' => 0, 'credit' => 90],
            ],
        ])->assertSessionHasErrors('lines');

        $this->assertDatabaseCount('journal_entries', 0);
        $this->actingAs($staff)->get('/accounting')->assertForbidden();
    }

    public function test_posted_journal_can_be_reversed_once_without_changing_original_lines(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $cash = DB::table('gl_accounts')->where('code', '1100')->firstOrFail();
        $expense = DB::table('gl_accounts')->where('code', '6100')->firstOrFail();

        $this->actingAs($finance)->post('/accounting/journals', [
            'journal_date' => today()->toDateString(),
            'memo' => 'Journal requiring reversal',
            'lines' => [
                ['gl_account_id' => $expense->id, 'description' => 'Expense line', 'debit' => 250000, 'credit' => 0],
                ['gl_account_id' => $cash->id, 'description' => 'Cash line', 'debit' => 0, 'credit' => 250000],
            ],
        ]);
        $original = DB::table('journal_entries')->firstOrFail();
        $originalLines = DB::table('journal_entry_lines')->where('journal_entry_id', $original->id)->orderBy('line_number')->get();
        $this->actingAs($finance)->post('/accounting/journals/'.$original->id.'/post')->assertRedirect();

        $response = $this->actingAs($finance)->post('/accounting/journals/'.$original->id.'/reverse', [
            'reversal_date' => today()->toDateString(),
            'reversal_reason' => 'Incorrect expense classification',
        ]);

        $reversal = DB::table('journal_entries')->where('reversal_of_id', $original->id)->firstOrFail();
        $response->assertRedirect('/accounting/journals/'.$reversal->id);
        $this->assertSame('posted', $reversal->status);
        $this->assertSame('reversal', $reversal->source_type);
        $this->assertSame($reversal->id, DB::table('journal_entries')->where('id', $original->id)->value('reversed_by_id'));
        $reversalLines = DB::table('journal_entry_lines')->where('journal_entry_id', $reversal->id)->orderBy('line_number')->get();
        $this->assertEquals($originalLines[0]->debit, $reversalLines[0]->credit);
        $this->assertEquals($originalLines[1]->credit, $reversalLines[1]->debit);
        $this->assertDatabaseHas('audit_logs', ['event' => 'journal_reversed', 'auditable_id' => $original->id]);

        $this->actingAs($finance)->post('/accounting/journals/'.$original->id.'/reverse', [
            'reversal_date' => today()->toDateString(),
            'reversal_reason' => 'Duplicate reversal attempt',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('journal_entries', 2);
    }

    public function test_journal_line_cannot_debit_and_credit_at_the_same_time(): void
    {
        $this->seed();
        $finance = User::where('email', 'finance@sams.local')->firstOrFail();
        $accounts = DB::table('gl_accounts')->where('allow_posting', true)->limit(2)->get();

        $this->actingAs($finance)->post('/accounting/journals', [
            'journal_date' => today()->toDateString(),
            'memo' => 'Invalid mixed side line',
            'lines' => [
                ['gl_account_id' => $accounts[0]->id, 'debit' => 100, 'credit' => 100],
                ['gl_account_id' => $accounts[1]->id, 'debit' => 100, 'credit' => 100],
            ],
        ])->assertSessionHasErrors('lines');

        $this->assertDatabaseCount('journal_entries', 0);
    }
}
