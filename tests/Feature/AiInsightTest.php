<?php

namespace Tests\Feature;

use App\Models\User;
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

        $this->actingAs($user)->post('/ai-insights/generate')->assertSessionHasErrors('ai');

        $run = DB::table('ai_insight_runs')->firstOrFail();
        $this->assertSame('failed', $run->status);
        $this->assertSame('configured-model', $run->model);
        $this->assertStringNotContainsString('sk-', (string) $run->error_message);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ai_insights_failed', 'auditable_id' => $run->id]);
    }
}
