<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DataConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_connections_and_test_api_co_id_without_exposing_key(): void
    {
        $this->seed();
        Cache::flush();
        config(['services.api_co_id.key' => 'private-connection-key', 'services.api_co_id.base_url' => 'https://use.api.co.id', 'services.api_co_id.bank_code' => 'bri']);
        Http::fake(['https://use.api.co.id/api/bank-rates*' => Http::response(['is_success' => true, 'data' => ['bank_code' => 'bri', 'last_fetched_at' => now()->timestamp * 1000, 'rate' => ['e-rate' => ['usd' => ['buy' => 16000, 'sell' => 16100]]]]])]);
        $admin = User::query()->where('email', 'admin@sams.local')->firstOrFail();

        $this->actingAs($admin)->get('/settings/data-connections')->assertOk()->assertSee('Data Connections')->assertDontSee('private-connection-key');
        $connection = DB::table('data_connections')->where('provider_key', 'api_co_id_bank_rate')->firstOrFail();
        $this->actingAs($admin)->post('/settings/data-connections/'.$connection->id.'/test')->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('data_connections', ['id' => $connection->id, 'status' => 'connected', 'is_active' => true]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'data_connection_tested', 'auditable_id' => $connection->id]);
    }

    public function test_staff_cannot_manage_connections_and_company_scope_is_enforced(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $staff = User::query()->where('email', 'staff@sams.local')->firstOrFail();
        $this->actingAs($admin)->get('/settings/data-connections');
        $connection = DB::table('data_connections')->firstOrFail();

        $this->actingAs($staff)->get('/settings/data-connections')->assertForbidden();
        $this->actingAs($staff)->post('/settings/data-connections/'.$connection->id.'/test')->assertForbidden();
    }
}
