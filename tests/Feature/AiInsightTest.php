<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AiInsightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AiInsightTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_generate_company_scoped_local_insights(): void
    {
        $this->seed();
        config(['ai.driver' => 'local']);
        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();

        $this->actingAs($user)->get('/ai-insights')->assertOk()->assertSee('AI Insight Center');
        $this->actingAs($user)->post('/ai-insights/generate')->assertRedirect();

        $run = DB::table('ai_insight_runs')->firstOrFail();
        $this->assertSame('local', $run->provider);
        $this->assertSame((int) DB::table('companies')->firstOrFail()->id, (int) $run->company_id);
        $this->assertNotEmpty(json_decode($run->output, true));
        $this->assertDatabaseHas('audit_logs', ['event' => 'ai_insights_generated', 'auditable_id' => $run->id]);
    }

    public function test_staff_without_intelligence_permission_cannot_open_or_generate_insights(): void
    {
        $this->seed();
        $staff = User::query()->where('email', 'staff@sams.local')->firstOrFail();

        $this->actingAs($staff)->get('/ai-insights')->assertForbidden();
        $this->actingAs($staff)->post('/ai-insights/generate')->assertForbidden();
        $this->assertDatabaseCount('ai_insight_runs', 0);
    }

    public function test_provider_failure_is_audited_without_exposing_a_key(): void
    {
        $this->seed();
        config(['ai.driver' => 'openai', 'ai.openai.api_key' => null, 'ai.openai.model' => 'configured-model']);
        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $company = DB::table('companies')->firstOrFail();
        DB::table('ai_company_settings')->insert(['company_id' => $company->id, 'is_enabled' => true, 'allow_external_provider' => true, 'monthly_request_limit' => 100, 'monthly_token_limit' => 100000, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->post('/ai-insights/generate')->assertSessionHasErrors('ai');

        $run = DB::table('ai_insight_runs')->firstOrFail();
        $this->assertSame('failed', $run->status);
        $this->assertSame('configured-model', $run->model);
        $this->assertStringNotContainsString('sk-', (string) $run->error_message);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ai_insights_failed', 'auditable_id' => $run->id]);
    }

    public function test_predictive_snapshot_scores_stock_price_supplier_and_maintenance_history(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $company = DB::table('companies')->firstOrFail();
        $branch = DB::table('branches')->firstOrFail();
        $supplier = DB::table('suppliers')->firstOrFail();
        $item = DB::table('items')->where('item_type', 'inventory')->firstOrFail();
        $unit = DB::table('units')->where('id', $item->base_unit_id)->firstOrFail();
        $location = DB::table('storage_locations')->firstOrFail();

        DB::table('stock_movements')->insert([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'storage_location_id' => $location->id,
            'item_id' => $item->id, 'movement_type' => 'test_usage', 'movement_at' => now()->subDays(10),
            'quantity' => -10, 'unit_cost' => 100, 'total_cost' => -1000, 'created_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([['AI-PO-1', 100], ['AI-PO-2', 200]] as $index => [$number, $price]) {
            $poId = DB::table('purchase_orders')->insertGetId([
                'company_id' => $company->id, 'branch_id' => $branch->id, 'supplier_id' => $supplier->id,
                'created_by' => $user->id, 'document_number' => $number, 'order_date' => now()->subDays(2 - $index),
                'status' => 'approved', 'subtotal' => $price, 'total_amount' => $price, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('purchase_order_items')->insert([
                'purchase_order_id' => $poId, 'item_id' => $item->id, 'unit_id' => $unit->id,
                'quantity' => 1, 'unit_price' => $price, 'line_total' => $price, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $assetId = DB::table('asset_registers')->insertGetId([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'item_id' => $item->id,
            'asset_number' => 'AI-ASSET-1', 'asset_name' => 'Predictive Test Asset', 'acquisition_date' => now()->subYear(),
            'condition' => 'poor', 'status' => 'active', 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([now()->subDays(240), now()->subDays(120)] as $index => $date) {
            DB::table('asset_maintenances')->insert([
                'company_id' => $company->id, 'branch_id' => $branch->id, 'asset_register_id' => $assetId,
                'requested_by' => $user->id, 'completed_by' => $user->id, 'document_number' => 'AI-MNT-'.$index,
                'status' => 'completed', 'request_date' => $date, 'completed_date' => $date, 'issue_description' => 'Historical maintenance',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('asset_maintenances')->insert([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'asset_register_id' => $assetId,
            'requested_by' => $user->id, 'document_number' => 'AI-MNT-OPEN', 'status' => 'open',
            'request_date' => now()->subDays(20), 'scheduled_date' => now()->subDays(5), 'issue_description' => 'Overdue maintenance',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $snapshot = app(AiInsightService::class)->snapshot((int) $company->id);
        $this->assertNotEmpty($snapshot['stock_forecasts']);
        $this->assertGreaterThan(0, $snapshot['stock_forecasts'][0]['recommended_reorder']);
        $this->assertNotEmpty($snapshot['price_anomalies']);
        $this->assertEquals(100.0, $snapshot['price_anomalies'][0]['deviation_percent']);
        $this->assertNotEmpty($snapshot['supplier_risks']);
        $prediction = collect($snapshot['maintenance_predictions'])->firstWhere('asset_id', $assetId);
        $this->assertSame('high', $prediction['risk_level']);
        $this->assertSame('high', $prediction['confidence']);
    }

    public function test_safe_natural_language_query_and_narrative_are_stored_and_audited(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();

        $this->actingAs($user)->post('/ai-insights/query', ['question' => 'Berapa sisa budget saat ini?'])->assertRedirect();
        $this->actingAs($user)->post('/ai-insights/query', ['question' => 'DROP TABLE users'])->assertRedirect();
        $this->actingAs($user)->post('/ai-insights/narrative')->assertRedirect();

        $this->assertDatabaseHas('ai_interactions', ['type' => 'query', 'intent' => 'budget']);
        $this->assertDatabaseHas('ai_interactions', ['type' => 'query', 'intent' => 'help']);
        $this->assertDatabaseHas('ai_interactions', ['type' => 'narrative', 'intent' => 'executive_summary']);
        $this->assertDatabaseHas('users', ['email' => 'admin@sams.local']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ai_query_answered']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ai_narrative_generated']);
    }

    public function test_company_quota_and_external_provider_guardrails_are_enforced(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $company = DB::table('companies')->firstOrFail();

        $this->actingAs($user)->put('/ai-insights/settings', [
            'is_enabled' => '1', 'monthly_request_limit' => 1, 'monthly_token_limit' => 1000,
        ])->assertRedirect();
        $this->actingAs($user)->post('/ai-insights/query', ['question' => 'status approval'])->assertRedirect();
        $this->actingAs($user)->post('/ai-insights/narrative')->assertSessionHasErrors('ai');

        config(['ai.driver' => 'openai', 'ai.openai.api_key' => 'not-used']);
        DB::table('ai_interactions')->delete();
        $this->actingAs($user)->post('/ai-insights/generate')->assertSessionHasErrors('ai');
        $this->assertDatabaseHas('ai_company_settings', ['company_id' => $company->id, 'allow_external_provider' => false]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ai_settings_updated']);
    }
}
