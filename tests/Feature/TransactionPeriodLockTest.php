<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionPeriodLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_lock_and_unlock_a_transaction_period(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();

        $this->actingAs($user)->get('/settings/period-locks')->assertOk();
        $this->actingAs($user)->post('/settings/period-locks', [
            'module' => 'procurement',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-31',
            'reason' => 'Monthly close',
        ])->assertRedirect();

        $lock = DB::table('transaction_period_locks')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', ['event' => 'transaction_period_locked', 'auditable_id' => $lock->id]);

        $this->actingAs($user)->delete('/settings/period-locks/'.$lock->id)->assertRedirect();
        $this->assertDatabaseMissing('transaction_period_locks', ['id' => $lock->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'transaction_period_unlocked', 'auditable_id' => $lock->id]);
    }

    public function test_locked_procurement_period_blocks_purchase_request_submission_without_committing_budget(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $company = DB::table('companies')->firstOrFail();
        $department = DB::table('departments')->where('code', 'PUR')->firstOrFail();
        $item = DB::table('items')->where('sku', 'ITM-RICE-01')->firstOrFail();
        $budgetLine = DB::table('budget_lines')->where('account_code', 'PUR-FNB')->firstOrFail();

        $this->actingAs($user)->post('/purchase-requests', [
            'department_id' => $department->id,
            'request_date' => '2026-07-11',
            'priority' => 'normal',
            'purpose' => 'Locked period test',
            'lines' => [[
                'item_id' => $item->id,
                'budget_line_id' => $budgetLine->id,
                'quantity' => 2,
                'estimated_unit_price' => 10000,
            ]],
        ]);

        $purchaseRequest = DB::table('purchase_requests')->where('purpose', 'Locked period test')->firstOrFail();
        DB::table('transaction_period_locks')->insert([
            'company_id' => $company->id,
            'module' => 'procurement',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-31',
            'reason' => 'Monthly close',
            'locked_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->post('/purchase-requests/'.$purchaseRequest->id.'/submit')
            ->assertSessionHasErrors('period');

        $this->assertDatabaseHas('purchase_requests', ['id' => $purchaseRequest->id, 'status' => 'draft']);
        $this->assertEquals(0.0, (float) DB::table('budget_lines')->where('id', $budgetLine->id)->value('committed_amount'));
        $this->assertDatabaseMissing('approval_requests', ['approvable_type' => 'purchase_request', 'approvable_id' => $purchaseRequest->id]);
    }
}
