<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_homepage_redirects_to_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/dashboard');
    }

    public function test_login_page_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_dashboard_redirects_guest_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_active_user_can_login(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'login-test@sams.local'],
            [
                'name' => 'Login Test',
                'password' => 'password',
                'role' => 'super_admin',
                'is_active' => true,
            ],
        );

        $response = $this->post('/login', [
            'email' => 'login-test@sams.local',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_super_admin_can_manage_users(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@sams.local')->firstOrFail();

        $indexResponse = $this->actingAs($admin)->get('/users');

        $indexResponse->assertOk();
        $indexResponse->assertSee('User Management');
        $indexResponse->assertSee('purchasing@sams.local');

        $storeResponse = $this->actingAs($admin)->post('/users', [
            'name' => 'Audit User',
            'email' => 'audit@sams.local',
            'role' => 'staff',
            'password' => 'password',
            'password_confirmation' => 'password',
            'is_active' => 1,
        ]);

        $storeResponse->assertRedirect('/users');
        $this->assertDatabaseHas('users', [
            'email' => 'audit@sams.local',
            'role' => 'staff',
            'is_active' => true,
        ]);

        $user = User::query()->where('email', 'audit@sams.local')->firstOrFail();

        $updateResponse = $this->actingAs($admin)->put('/users/'.$user->id, [
            'name' => 'Audit Finance',
            'email' => 'audit-finance@sams.local',
            'role' => 'finance',
            'password' => '',
            'password_confirmation' => '',
            'is_active' => 0,
        ]);

        $updateResponse->assertRedirect('/users');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'audit-finance@sams.local',
            'role' => 'finance',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user_created',
            'auditable_type' => 'user',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user_updated',
            'auditable_type' => 'user',
        ]);
    }

    public function test_non_super_admin_cannot_access_user_management(): void
    {
        $this->seed();

        $warehouse = User::query()->where('email', 'warehouse@sams.local')->firstOrFail();

        $response = $this->actingAs($warehouse)->get('/users');

        $response->assertForbidden();
    }

    public function test_authenticated_user_can_open_master_supplier_page(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();

        $response = $this->actingAs($user)->get('/master/suppliers');

        $response->assertOk();
        $response->assertSee('Supplier');
    }

    public function test_authenticated_user_can_open_master_data_overview(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();

        $response = $this->actingAs($user)->get('/master');

        $response->assertOk();
        $response->assertSee('Master Data');
        $response->assertSee('Item');
    }

    public function test_authenticated_user_can_create_supplier(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();

        $response = $this->actingAs($user)->post('/master/suppliers', [
            'code' => 'SUP-TEST',
            'name' => 'Supplier Test',
            'contact_person' => 'Rendy',
            'phone' => '0800000001',
            'email' => 'supplier-test@example.test',
            'payment_terms_days' => 7,
        ]);

        $response->assertRedirect('/master/suppliers');
        $this->assertDatabaseHas('suppliers', [
            'code' => 'SUP-TEST',
            'name' => 'Supplier Test',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'master_created',
            'auditable_type' => 'suppliers',
        ]);
    }

    public function test_super_admin_can_open_audit_trail(): void
    {
        $this->test_authenticated_user_can_create_supplier();

        $admin = User::query()->where('email', 'admin@sams.local')->firstOrFail();

        $response = $this->actingAs($admin)->get('/audit-logs');

        $response->assertOk();
        $response->assertSee('Audit Trail');
        $response->assertSee('master created');
        $response->assertSee('suppliers');
    }

    public function test_non_super_admin_cannot_open_audit_trail(): void
    {
        $this->seed();

        $warehouse = User::query()->where('email', 'warehouse@sams.local')->firstOrFail();

        $response = $this->actingAs($warehouse)->get('/audit-logs');

        $response->assertForbidden();
    }

    public function test_staff_user_cannot_modify_master_data(): void
    {
        $this->seed();

        $staff = User::query()->where('email', 'staff@sams.local')->firstOrFail();

        $response = $this->actingAs($staff)->post('/master/suppliers', [
            'code' => 'SUP-DENIED',
            'name' => 'Denied Supplier',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('suppliers', [
            'code' => 'SUP-DENIED',
        ]);
    }

    public function test_realistic_demo_master_data_is_seeded(): void
    {
        $this->seed();

        $this->assertDatabaseHas('item_categories', ['code' => 'FOOD', 'name' => 'Food & Beverage']);
        $this->assertDatabaseHas('storage_locations', ['code' => 'KITCHEN', 'name' => 'Main Kitchen']);
        $this->assertDatabaseHas('suppliers', ['code' => 'SUP-FOOD-01', 'name' => 'Bali Fresh Market']);
        $this->assertDatabaseHas('items', ['sku' => 'ITM-RICE-01', 'name' => 'Beras Premium 25kg']);
        $this->assertDatabaseHas('items', ['sku' => 'ITM-SERVICE-AC', 'item_type' => 'service']);
        $this->assertDatabaseHas('budget_lines', ['account_code' => 'PUR-FNB', 'description' => 'Food & Beverage Purchasing']);
    }

    public function test_authenticated_user_can_open_purchase_request_page(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();

        $response = $this->actingAs($user)->get('/purchase-requests');

        $response->assertOk();
        $response->assertSee('Purchase Request');
    }

    public function test_authenticated_user_can_create_and_submit_purchase_request(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $department = DB::table('departments')->where('code', 'PUR')->firstOrFail();
        $item = DB::table('items')->where('sku', 'ITM-RICE-01')->firstOrFail();

        $response = $this->actingAs($user)->post('/purchase-requests', [
            'department_id' => $department->id,
            'request_date' => now()->format('Y-m-d'),
            'required_date' => now()->addDays(3)->format('Y-m-d'),
            'priority' => 'normal',
            'purpose' => 'Kebutuhan operasional kitchen',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 25,
                    'estimated_unit_price' => 14500,
                    'notes' => 'Sample PR',
                ],
            ],
        ]);

        $purchaseRequest = DB::table('purchase_requests')
            ->where('purpose', 'Kebutuhan operasional kitchen')
            ->firstOrFail();

        $response->assertRedirect('/purchase-requests/'.$purchaseRequest->id);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'draft',
            'estimated_total' => 362500,
        ]);

        $submitResponse = $this->actingAs($user)->post('/purchase-requests/'.$purchaseRequest->id.'/submit');

        $submitResponse->assertRedirect('/purchase-requests/'.$purchaseRequest->id);
        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'submitted',
        ]);
    }

    public function test_authenticated_user_can_edit_draft_purchase_request(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $department = DB::table('departments')->where('code', 'PUR')->firstOrFail();
        $rice = DB::table('items')->where('sku', 'ITM-RICE-01')->firstOrFail();
        $coffee = DB::table('items')->where('sku', 'ITM-COFFEE-01')->firstOrFail();

        $this->actingAs($user)->post('/purchase-requests', [
            'department_id' => $department->id,
            'request_date' => now()->format('Y-m-d'),
            'required_date' => now()->addDays(3)->format('Y-m-d'),
            'priority' => 'normal',
            'purpose' => 'Draft sebelum edit',
            'lines' => [
                [
                    'item_id' => $rice->id,
                    'quantity' => 10,
                    'estimated_unit_price' => 14500,
                ],
            ],
        ]);

        $purchaseRequest = DB::table('purchase_requests')
            ->where('purpose', 'Draft sebelum edit')
            ->firstOrFail();

        $response = $this->actingAs($user)->put('/purchase-requests/'.$purchaseRequest->id, [
            'department_id' => $department->id,
            'request_date' => now()->format('Y-m-d'),
            'required_date' => now()->addDays(5)->format('Y-m-d'),
            'priority' => 'high',
            'purpose' => 'Draft setelah edit',
            'lines' => [
                [
                    'item_id' => $coffee->id,
                    'quantity' => 2,
                    'estimated_unit_price' => 180000,
                    'notes' => 'Ganti item',
                ],
            ],
        ]);

        $response->assertRedirect('/purchase-requests/'.$purchaseRequest->id);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'priority' => 'high',
            'purpose' => 'Draft setelah edit',
            'estimated_total' => 360000,
        ]);

        $this->assertDatabaseMissing('purchase_request_items', [
            'purchase_request_id' => $purchaseRequest->id,
            'item_id' => $rice->id,
        ]);

        $this->assertDatabaseHas('purchase_request_items', [
            'purchase_request_id' => $purchaseRequest->id,
            'item_id' => $coffee->id,
            'estimated_total' => 360000,
        ]);
    }

    public function test_submitted_purchase_request_cannot_be_edited(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $department = DB::table('departments')->where('code', 'PUR')->firstOrFail();
        $item = DB::table('items')->where('sku', 'ITM-RICE-01')->firstOrFail();

        $this->actingAs($user)->post('/purchase-requests', [
            'department_id' => $department->id,
            'request_date' => now()->format('Y-m-d'),
            'required_date' => now()->addDays(3)->format('Y-m-d'),
            'priority' => 'normal',
            'purpose' => 'Submitted tidak boleh edit',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 10,
                    'estimated_unit_price' => 14500,
                ],
            ],
        ]);

        $purchaseRequest = DB::table('purchase_requests')
            ->where('purpose', 'Submitted tidak boleh edit')
            ->firstOrFail();

        $this->actingAs($user)->post('/purchase-requests/'.$purchaseRequest->id.'/submit');

        $response = $this->actingAs($user)->put('/purchase-requests/'.$purchaseRequest->id, [
            'department_id' => $department->id,
            'request_date' => now()->format('Y-m-d'),
            'priority' => 'urgent',
            'purpose' => 'Percobaan edit submitted',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 99,
                    'estimated_unit_price' => 14500,
                ],
            ],
        ]);

        $response->assertRedirect('/purchase-requests/'.$purchaseRequest->id);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'submitted',
            'purpose' => 'Submitted tidak boleh edit',
        ]);
    }

    public function test_purchase_request_submit_commits_budget(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $department = DB::table('departments')->where('code', 'PUR')->firstOrFail();
        $item = DB::table('items')->where('sku', 'ITM-RICE-01')->firstOrFail();
        $budgetLine = DB::table('budget_lines')->where('account_code', 'PUR-FNB')->firstOrFail();

        $this->actingAs($user)->post('/purchase-requests', [
            'department_id' => $department->id,
            'request_date' => now()->format('Y-m-d'),
            'priority' => 'normal',
            'purpose' => 'PR dengan budget',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'budget_line_id' => $budgetLine->id,
                    'quantity' => 10,
                    'estimated_unit_price' => 14500,
                ],
            ],
        ]);

        $purchaseRequest = DB::table('purchase_requests')
            ->where('purpose', 'PR dengan budget')
            ->firstOrFail();

        $this->actingAs($user)->post('/purchase-requests/'.$purchaseRequest->id.'/submit');

        $this->assertDatabaseHas('budget_lines', [
            'id' => $budgetLine->id,
            'committed_amount' => 145000,
        ]);
    }

    public function test_budget_control_page_shows_committed_budget(): void
    {
        $this->test_purchase_request_submit_commits_budget();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();

        $response = $this->actingAs($user)->get('/budget-control');

        $response->assertOk();
        $response->assertSee('Budget Control');
        $response->assertSee('PUR-FNB');
        $response->assertSee('Committed');
        $response->assertSee('Rp 145.000');
    }

    public function test_budget_control_print_page_can_be_rendered(): void
    {
        $this->test_purchase_request_submit_commits_budget();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();

        $response = $this->actingAs($user)->get('/budget-control/print');

        $response->assertOk();
        $response->assertSee('BUDGET CONTROL REPORT');
        $response->assertSee('PUR-FNB');
        $response->assertSee('Allocated');
        $response->assertSee('Remaining');
    }

    public function test_staff_user_cannot_approve_purchase_request(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $staff = User::query()->where('email', 'staff@sams.local')->firstOrFail();
        $department = DB::table('departments')->where('code', 'PUR')->firstOrFail();
        $item = DB::table('items')->where('sku', 'ITM-RICE-01')->firstOrFail();

        $this->actingAs($admin)->post('/purchase-requests', [
            'department_id' => $department->id,
            'request_date' => now()->format('Y-m-d'),
            'priority' => 'normal',
            'purpose' => 'PR staff tidak boleh approve',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 10,
                    'estimated_unit_price' => 14500,
                ],
            ],
        ]);

        $purchaseRequest = DB::table('purchase_requests')
            ->where('purpose', 'PR staff tidak boleh approve')
            ->firstOrFail();

        $this->actingAs($admin)->post('/purchase-requests/'.$purchaseRequest->id.'/submit');

        $response = $this->actingAs($staff)->post('/purchase-requests/'.$purchaseRequest->id.'/approve');

        $response->assertForbidden();
        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'submitted',
        ]);
    }

    public function test_purchase_request_rejects_over_budget_line(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $department = DB::table('departments')->where('code', 'PUR')->firstOrFail();
        $item = DB::table('items')->where('sku', 'ITM-RICE-01')->firstOrFail();
        $budgetLine = DB::table('budget_lines')->where('account_code', 'PUR-FNB')->firstOrFail();

        $response = $this->actingAs($user)->from('/purchase-requests/create')->post('/purchase-requests', [
            'department_id' => $department->id,
            'request_date' => now()->format('Y-m-d'),
            'priority' => 'normal',
            'purpose' => 'PR over budget',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'budget_line_id' => $budgetLine->id,
                    'quantity' => 1,
                    'estimated_unit_price' => 999999999,
                ],
            ],
        ]);

        $response->assertRedirect('/purchase-requests/create');
        $response->assertSessionHasErrors('lines');
        $this->assertDatabaseMissing('purchase_requests', [
            'purpose' => 'PR over budget',
        ]);
    }

    public function test_submitted_purchase_request_can_be_approved(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $department = DB::table('departments')->where('code', 'PUR')->firstOrFail();
        $item = DB::table('items')->where('sku', 'ITM-RICE-01')->firstOrFail();

        $this->actingAs($user)->post('/purchase-requests', [
            'department_id' => $department->id,
            'request_date' => now()->format('Y-m-d'),
            'priority' => 'normal',
            'purpose' => 'PR untuk approval',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 10,
                    'estimated_unit_price' => 14500,
                ],
            ],
        ]);

        $purchaseRequest = DB::table('purchase_requests')
            ->where('purpose', 'PR untuk approval')
            ->firstOrFail();

        $this->actingAs($user)->post('/purchase-requests/'.$purchaseRequest->id.'/submit');
        $response = $this->actingAs($user)->post('/purchase-requests/'.$purchaseRequest->id.'/approve', [
            'comments' => 'Approved by test',
        ]);

        $response->assertRedirect('/purchase-requests/'.$purchaseRequest->id);
        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('approval_actions', [
            'action' => 'approved',
            'comments' => 'Approved by test',
        ]);
    }

    public function test_purchase_request_print_page_can_be_rendered(): void
    {
        $this->test_submitted_purchase_request_can_be_approved();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $purchaseRequest = DB::table('purchase_requests')
            ->where('purpose', 'PR untuk approval')
            ->firstOrFail();

        $response = $this->actingAs($user)->get('/purchase-requests/'.$purchaseRequest->id.'/print');

        $response->assertOk();
        $response->assertSee('PURCHASE REQUEST');
        $response->assertSee($purchaseRequest->document_number);
        $response->assertSee('Purchasing');
        $response->assertSee('ITM-RICE-01');
        $response->assertSee('Grand Total Estimasi');
        $response->assertSee('Diajukan oleh');
        $response->assertSee('Diperiksa oleh');
        $response->assertSee('Disetujui oleh');
    }

    public function test_rejected_purchase_request_releases_committed_budget(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $department = DB::table('departments')->where('code', 'PUR')->firstOrFail();
        $item = DB::table('items')->where('sku', 'ITM-RICE-01')->firstOrFail();
        $budgetLine = DB::table('budget_lines')->where('account_code', 'PUR-FNB')->firstOrFail();

        $this->actingAs($user)->post('/purchase-requests', [
            'department_id' => $department->id,
            'request_date' => now()->format('Y-m-d'),
            'priority' => 'normal',
            'purpose' => 'PR untuk reject',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'budget_line_id' => $budgetLine->id,
                    'quantity' => 10,
                    'estimated_unit_price' => 14500,
                ],
            ],
        ]);

        $purchaseRequest = DB::table('purchase_requests')
            ->where('purpose', 'PR untuk reject')
            ->firstOrFail();

        $this->actingAs($user)->post('/purchase-requests/'.$purchaseRequest->id.'/submit');
        $this->assertDatabaseHas('budget_lines', [
            'id' => $budgetLine->id,
            'committed_amount' => 145000,
        ]);

        $response = $this->actingAs($user)->post('/purchase-requests/'.$purchaseRequest->id.'/reject', [
            'comments' => 'Rejected by test',
        ]);

        $response->assertRedirect('/purchase-requests/'.$purchaseRequest->id);
        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'rejected',
        ]);
        $this->assertDatabaseHas('budget_lines', [
            'id' => $budgetLine->id,
            'committed_amount' => 0,
        ]);
        $this->assertDatabaseHas('approval_actions', [
            'action' => 'rejected',
            'comments' => 'Rejected by test',
        ]);
    }

    public function test_purchase_order_can_be_created_from_approved_purchase_request(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $department = DB::table('departments')->where('code', 'PUR')->firstOrFail();
        $item = DB::table('items')->where('sku', 'ITM-RICE-01')->firstOrFail();
        $supplier = DB::table('suppliers')->where('code', 'SUP-FOOD-01')->firstOrFail();

        $this->actingAs($user)->post('/purchase-requests', [
            'department_id' => $department->id,
            'request_date' => now()->format('Y-m-d'),
            'priority' => 'normal',
            'purpose' => 'PR to PO',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 10,
                    'estimated_unit_price' => 14500,
                ],
            ],
        ]);

        $purchaseRequest = DB::table('purchase_requests')
            ->where('purpose', 'PR to PO')
            ->firstOrFail();

        $this->actingAs($user)->post('/purchase-requests/'.$purchaseRequest->id.'/submit');
        $this->actingAs($user)->post('/purchase-requests/'.$purchaseRequest->id.'/approve');

        $response = $this->actingAs($user)->post('/purchase-orders/from-pr/'.$purchaseRequest->id, [
            'supplier_id' => $supplier->id,
            'order_date' => now()->format('Y-m-d'),
            'expected_date' => now()->addDays(7)->format('Y-m-d'),
            'notes' => 'PO from test',
        ]);

        $purchaseOrder = DB::table('purchase_orders')
            ->where('purchase_request_id', $purchaseRequest->id)
            ->firstOrFail();

        $response->assertRedirect('/purchase-orders/'.$purchaseOrder->id);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'draft',
            'supplier_id' => $supplier->id,
            'total_amount' => 145000,
        ]);

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'item_id' => $item->id,
            'line_total' => 145000,
        ]);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'converted_to_po',
        ]);
    }

    public function test_purchase_order_can_be_submitted_and_approved(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $department = DB::table('departments')->where('code', 'PUR')->firstOrFail();
        $item = DB::table('items')->where('sku', 'ITM-RICE-01')->firstOrFail();
        $supplier = DB::table('suppliers')->where('code', 'SUP-FOOD-01')->firstOrFail();

        $this->actingAs($user)->post('/purchase-requests', [
            'department_id' => $department->id,
            'request_date' => now()->format('Y-m-d'),
            'priority' => 'normal',
            'purpose' => 'PO approval flow',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 10,
                    'estimated_unit_price' => 14500,
                ],
            ],
        ]);

        $purchaseRequest = DB::table('purchase_requests')
            ->where('purpose', 'PO approval flow')
            ->firstOrFail();

        $this->actingAs($user)->post('/purchase-requests/'.$purchaseRequest->id.'/submit');
        $this->actingAs($user)->post('/purchase-requests/'.$purchaseRequest->id.'/approve');
        $this->actingAs($user)->post('/purchase-orders/from-pr/'.$purchaseRequest->id, [
            'supplier_id' => $supplier->id,
            'order_date' => now()->format('Y-m-d'),
        ]);

        $purchaseOrder = DB::table('purchase_orders')
            ->where('purchase_request_id', $purchaseRequest->id)
            ->firstOrFail();

        $submitResponse = $this->actingAs($user)->post('/purchase-orders/'.$purchaseOrder->id.'/submit');

        $submitResponse->assertRedirect('/purchase-orders/'.$purchaseOrder->id);
        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'submitted',
        ]);

        $approveResponse = $this->actingAs($user)->post('/purchase-orders/'.$purchaseOrder->id.'/approve');

        $approveResponse->assertRedirect('/purchase-orders/'.$purchaseOrder->id);
        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'approved',
        ]);
    }

    public function test_approval_center_shows_pending_purchase_request_and_purchase_order(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $department = DB::table('departments')->where('code', 'PUR')->firstOrFail();
        $item = DB::table('items')->where('sku', 'ITM-RICE-01')->firstOrFail();
        $supplier = DB::table('suppliers')->where('code', 'SUP-FOOD-01')->firstOrFail();

        $this->actingAs($user)->post('/purchase-requests', [
            'department_id' => $department->id,
            'request_date' => now()->format('Y-m-d'),
            'priority' => 'urgent',
            'purpose' => 'PR approval center',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 5,
                    'estimated_unit_price' => 14500,
                ],
            ],
        ]);

        $pendingPurchaseRequest = DB::table('purchase_requests')
            ->where('purpose', 'PR approval center')
            ->firstOrFail();

        $this->actingAs($user)->post('/purchase-requests/'.$pendingPurchaseRequest->id.'/submit');

        $this->actingAs($user)->post('/purchase-requests', [
            'department_id' => $department->id,
            'request_date' => now()->format('Y-m-d'),
            'priority' => 'normal',
            'purpose' => 'PO approval center source',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 10,
                    'estimated_unit_price' => 14500,
                ],
            ],
        ]);

        $sourcePurchaseRequest = DB::table('purchase_requests')
            ->where('purpose', 'PO approval center source')
            ->firstOrFail();

        $this->actingAs($user)->post('/purchase-requests/'.$sourcePurchaseRequest->id.'/submit');
        $this->actingAs($user)->post('/purchase-requests/'.$sourcePurchaseRequest->id.'/approve');
        $this->actingAs($user)->post('/purchase-orders/from-pr/'.$sourcePurchaseRequest->id, [
            'supplier_id' => $supplier->id,
            'order_date' => now()->format('Y-m-d'),
        ]);

        $purchaseOrder = DB::table('purchase_orders')
            ->where('purchase_request_id', $sourcePurchaseRequest->id)
            ->firstOrFail();

        $this->actingAs($user)->post('/purchase-orders/'.$purchaseOrder->id.'/submit');

        $response = $this->actingAs($user)->get('/approvals');

        $response->assertOk();
        $response->assertSee('Approval Center');
        $response->assertSee($pendingPurchaseRequest->document_number);
        $response->assertSee($purchaseOrder->document_number);
        $response->assertSee('Bali Fresh Market');
        $response->assertSee('High Priority');
    }

    public function test_staff_user_cannot_open_approval_center(): void
    {
        $this->seed();

        $staff = User::query()->where('email', 'staff@sams.local')->firstOrFail();

        $response = $this->actingAs($staff)->get('/approvals');

        $response->assertForbidden();
    }

    public function test_purchase_order_print_page_can_be_rendered(): void
    {
        $this->test_purchase_order_can_be_created_from_approved_purchase_request();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $purchaseOrder = DB::table('purchase_orders')->firstOrFail();

        $response = $this->actingAs($user)->get('/purchase-orders/'.$purchaseOrder->id.'/print');

        $response->assertOk();
        $response->assertSee('PURCHASE ORDER');
        $response->assertSee($purchaseOrder->document_number);
        $response->assertSee('Bali Fresh Market');
        $response->assertSee('ITM-RICE-01');
        $response->assertSee('Grand Total');
        $response->assertSee('Dibuat oleh');
        $response->assertSee('Disetujui oleh');
        $response->assertSee('Diterima Supplier');
    }

    public function test_goods_receipt_can_be_created_and_posted_from_approved_purchase_order(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $department = DB::table('departments')->where('code', 'PUR')->firstOrFail();
        $item = DB::table('items')->where('sku', 'ITM-RICE-01')->firstOrFail();
        $supplier = DB::table('suppliers')->where('code', 'SUP-FOOD-01')->firstOrFail();
        $location = DB::table('storage_locations')->where('code', 'MAIN-WH')->firstOrFail();
        $budgetLine = DB::table('budget_lines')->where('account_code', 'PUR-FNB')->firstOrFail();

        $this->actingAs($user)->post('/purchase-requests', [
            'department_id' => $department->id,
            'request_date' => now()->format('Y-m-d'),
            'priority' => 'normal',
            'purpose' => 'GR flow',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'budget_line_id' => $budgetLine->id,
                    'quantity' => 10,
                    'estimated_unit_price' => 14500,
                ],
            ],
        ]);

        $purchaseRequest = DB::table('purchase_requests')
            ->where('purpose', 'GR flow')
            ->firstOrFail();

        $this->actingAs($user)->post('/purchase-requests/'.$purchaseRequest->id.'/submit');
        $this->actingAs($user)->post('/purchase-requests/'.$purchaseRequest->id.'/approve');
        $this->actingAs($user)->post('/purchase-orders/from-pr/'.$purchaseRequest->id, [
            'supplier_id' => $supplier->id,
            'order_date' => now()->format('Y-m-d'),
        ]);

        $purchaseOrder = DB::table('purchase_orders')
            ->where('purchase_request_id', $purchaseRequest->id)
            ->firstOrFail();

        $this->actingAs($user)->post('/purchase-orders/'.$purchaseOrder->id.'/submit');
        $this->actingAs($user)->post('/purchase-orders/'.$purchaseOrder->id.'/approve');

        $purchaseOrderItem = DB::table('purchase_order_items')
            ->where('purchase_order_id', $purchaseOrder->id)
            ->firstOrFail();

        $response = $this->actingAs($user)->post('/goods-receipts/from-po/'.$purchaseOrder->id, [
            'storage_location_id' => $location->id,
            'received_at' => now()->format('Y-m-d\TH:i'),
            'supplier_delivery_number' => 'SJ-TEST-001',
            'notes' => 'GR from test',
            'lines' => [
                [
                    'purchase_order_item_id' => $purchaseOrderItem->id,
                    'accepted_quantity' => 10,
                    'rejected_quantity' => 0,
                ],
            ],
        ]);

        $goodsReceipt = DB::table('goods_receipts')
            ->where('purchase_order_id', $purchaseOrder->id)
            ->firstOrFail();

        $response->assertRedirect('/goods-receipts/'.$goodsReceipt->id);

        $postResponse = $this->actingAs($user)->post('/goods-receipts/'.$goodsReceipt->id.'/post');

        $postResponse->assertRedirect('/goods-receipts/'.$goodsReceipt->id);
        $this->assertDatabaseHas('goods_receipts', [
            'id' => $goodsReceipt->id,
            'status' => 'posted',
        ]);
        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $purchaseOrderItem->id,
            'received_quantity' => 10,
        ]);
        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'received',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'source_type' => 'goods_receipt',
            'source_id' => $goodsReceipt->id,
            'item_id' => $item->id,
            'quantity' => 10,
        ]);
        $this->assertDatabaseHas('budget_lines', [
            'id' => $budgetLine->id,
            'committed_amount' => 0,
            'actual_amount' => 145000,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'goods_receipt_posted',
            'auditable_type' => 'goods_receipt',
            'auditable_id' => $goodsReceipt->id,
        ]);
    }

    public function test_goods_receipt_print_page_can_be_rendered(): void
    {
        $this->test_goods_receipt_can_be_created_and_posted_from_approved_purchase_order();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $goodsReceipt = DB::table('goods_receipts')->firstOrFail();

        $response = $this->actingAs($user)->get('/goods-receipts/'.$goodsReceipt->id.'/print');

        $response->assertOk();
        $response->assertSee('GOODS RECEIPT');
        $response->assertSee($goodsReceipt->document_number);
        $response->assertSee('MAIN-WH');
        $response->assertSee('ITM-RICE-01');
        $response->assertSee('Diterima oleh');
        $response->assertSee('Diperiksa oleh');
        $response->assertSee('Diketahui oleh');
    }

    public function test_asset_register_can_create_and_show_asset(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $item = DB::table('items')->where('sku', 'ITM-LAPTOP-OPS')->firstOrFail();
        $department = DB::table('departments')->where('code', 'OPS')->firstOrFail();
        $location = DB::table('storage_locations')->where('code', 'MAIN-WH')->firstOrFail();

        $response = $this->actingAs($user)->post('/assets', [
            'item_id' => $item->id,
            'department_id' => $department->id,
            'storage_location_id' => $location->id,
            'asset_name' => 'Laptop Operations FO-01',
            'asset_number' => '',
            'serial_number' => 'SN-LAP-0001',
            'acquisition_date' => now()->format('Y-m-d'),
            'acquisition_cost' => 9500000,
            'condition' => 'good',
            'status' => 'active',
            'notes' => 'Asset sample untuk front office.',
        ]);

        $asset = DB::table('asset_registers')
            ->where('asset_name', 'Laptop Operations FO-01')
            ->firstOrFail();

        $response->assertRedirect('/assets/'.$asset->id);

        $this->assertDatabaseHas('asset_registers', [
            'id' => $asset->id,
            'asset_number' => $asset->asset_number,
            'serial_number' => 'SN-LAP-0001',
            'condition' => 'good',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'asset_created',
            'auditable_type' => 'asset_register',
            'auditable_id' => $asset->id,
        ]);

        $showResponse = $this->actingAs($user)->get('/assets/'.$asset->id);

        $showResponse->assertOk();
        $showResponse->assertSee('Laptop Operations FO-01');
        $showResponse->assertSee($asset->asset_number);
        $showResponse->assertSee('SN-LAP-0001');
    }

    public function test_asset_register_index_and_print_can_be_rendered(): void
    {
        $this->test_asset_register_can_create_and_show_asset();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $asset = DB::table('asset_registers')->firstOrFail();

        $indexResponse = $this->actingAs($user)->get('/assets');

        $indexResponse->assertOk();
        $indexResponse->assertSee('Asset Register');
        $indexResponse->assertSee('Laptop Operations FO-01');
        $indexResponse->assertSee('Acquisition Value');

        $printResponse = $this->actingAs($user)->get('/assets/'.$asset->id.'/print');

        $printResponse->assertOk();
        $printResponse->assertSee('ASSET CARD');
        $printResponse->assertSee($asset->asset_number);
        $printResponse->assertSee('Laptop Operations FO-01');
        $printResponse->assertSee('Diperiksa oleh');
    }

    public function test_asset_can_be_registered_from_posted_goods_receipt_item(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $department = DB::table('departments')->where('code', 'OPS')->firstOrFail();
        $item = DB::table('items')->where('sku', 'ITM-LAPTOP-OPS')->firstOrFail();
        $supplier = DB::table('suppliers')->firstOrFail();
        $location = DB::table('storage_locations')->where('code', 'MAIN-WH')->firstOrFail();

        $this->actingAs($user)->post('/purchase-requests', [
            'department_id' => $department->id,
            'request_date' => now()->format('Y-m-d'),
            'priority' => 'normal',
            'purpose' => 'Asset dari GR',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 1,
                    'estimated_unit_price' => 9500000,
                ],
            ],
        ]);

        $purchaseRequest = DB::table('purchase_requests')
            ->where('purpose', 'Asset dari GR')
            ->firstOrFail();

        $this->actingAs($user)->post('/purchase-requests/'.$purchaseRequest->id.'/submit');
        $this->actingAs($user)->post('/purchase-requests/'.$purchaseRequest->id.'/approve');
        $this->actingAs($user)->post('/purchase-orders/from-pr/'.$purchaseRequest->id, [
            'supplier_id' => $supplier->id,
            'order_date' => now()->format('Y-m-d'),
        ]);

        $purchaseOrder = DB::table('purchase_orders')
            ->where('purchase_request_id', $purchaseRequest->id)
            ->firstOrFail();

        $this->actingAs($user)->post('/purchase-orders/'.$purchaseOrder->id.'/submit');
        $this->actingAs($user)->post('/purchase-orders/'.$purchaseOrder->id.'/approve');

        $purchaseOrderItem = DB::table('purchase_order_items')
            ->where('purchase_order_id', $purchaseOrder->id)
            ->firstOrFail();

        $this->actingAs($user)->post('/goods-receipts/from-po/'.$purchaseOrder->id, [
            'storage_location_id' => $location->id,
            'received_at' => now()->format('Y-m-d\TH:i'),
            'lines' => [
                [
                    'purchase_order_item_id' => $purchaseOrderItem->id,
                    'accepted_quantity' => 1,
                    'rejected_quantity' => 0,
                ],
            ],
        ]);

        $goodsReceipt = DB::table('goods_receipts')
            ->where('purchase_order_id', $purchaseOrder->id)
            ->firstOrFail();

        $this->actingAs($user)->post('/goods-receipts/'.$goodsReceipt->id.'/post');

        $goodsReceiptItem = DB::table('goods_receipt_items')
            ->where('goods_receipt_id', $goodsReceipt->id)
            ->firstOrFail();

        $createResponse = $this->actingAs($user)->get('/assets/create/from-gr-item/'.$goodsReceiptItem->id);

        $createResponse->assertOk();
        $createResponse->assertSee('Source GR');
        $createResponse->assertSee('ITM-LAPTOP-OPS');

        $storeResponse = $this->actingAs($user)->post('/assets', [
            'goods_receipt_item_id' => $goodsReceiptItem->id,
            'item_id' => $item->id,
            'department_id' => $department->id,
            'storage_location_id' => $location->id,
            'asset_name' => 'Laptop dari GR',
            'asset_number' => '',
            'serial_number' => 'GR-ASSET-001',
            'acquisition_date' => now()->format('Y-m-d'),
            'acquisition_cost' => 9500000,
            'condition' => 'good',
            'status' => 'active',
            'notes' => 'Asset dibuat dari Goods Receipt.',
        ]);

        $asset = DB::table('asset_registers')
            ->where('goods_receipt_item_id', $goodsReceiptItem->id)
            ->firstOrFail();

        $storeResponse->assertRedirect('/assets/'.$asset->id);
        $this->assertDatabaseHas('asset_registers', [
            'id' => $asset->id,
            'goods_receipt_item_id' => $goodsReceiptItem->id,
            'asset_name' => 'Laptop dari GR',
            'serial_number' => 'GR-ASSET-001',
        ]);

        $goodsReceiptResponse = $this->actingAs($user)->get('/goods-receipts/'.$goodsReceipt->id);

        $goodsReceiptResponse->assertOk();
        $goodsReceiptResponse->assertSee($asset->asset_number);
        $goodsReceiptResponse->assertSee('ITM-LAPTOP-OPS');
    }

    public function test_asset_maintenance_can_be_created_completed_and_printed(): void
    {
        $this->test_asset_register_can_create_and_show_asset();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $asset = DB::table('asset_registers')
            ->where('asset_name', 'Laptop Operations FO-01')
            ->firstOrFail();

        $createResponse = $this->actingAs($user)->get('/assets/'.$asset->id.'/maintenances/create');

        $createResponse->assertOk();
        $createResponse->assertSee('Buat Work Order');
        $createResponse->assertSee($asset->asset_number);

        $storeResponse = $this->actingAs($user)->post('/assets/'.$asset->id.'/maintenances', [
            'maintenance_type' => 'corrective',
            'priority' => 'high',
            'request_date' => now()->format('Y-m-d'),
            'scheduled_date' => now()->addDay()->format('Y-m-d'),
            'vendor_name' => 'Bali Tech Service',
            'estimated_cost' => 450000,
            'issue_description' => 'Laptop lambat dan perlu pengecekan SSD.',
        ]);

        $maintenance = DB::table('asset_maintenances')
            ->where('asset_register_id', $asset->id)
            ->firstOrFail();

        $storeResponse->assertRedirect('/asset-maintenances/'.$maintenance->id);
        $this->assertDatabaseHas('asset_maintenances', [
            'id' => $maintenance->id,
            'status' => 'open',
            'priority' => 'high',
            'vendor_name' => 'Bali Tech Service',
        ]);
        $this->assertDatabaseHas('asset_registers', [
            'id' => $asset->id,
            'status' => 'maintenance',
        ]);

        $showResponse = $this->actingAs($user)->get('/asset-maintenances/'.$maintenance->id);

        $showResponse->assertOk();
        $showResponse->assertSee('Maintenance Work Order');
        $showResponse->assertSee('Laptop lambat');

        $completeResponse = $this->actingAs($user)->post('/asset-maintenances/'.$maintenance->id.'/complete', [
            'completed_date' => now()->format('Y-m-d'),
            'actual_cost' => 425000,
            'resolution_notes' => 'SSD dicek dan sistem dibersihkan.',
            'asset_condition' => 'good',
            'asset_status' => 'active',
        ]);

        $completeResponse->assertRedirect('/asset-maintenances/'.$maintenance->id);
        $this->assertDatabaseHas('asset_maintenances', [
            'id' => $maintenance->id,
            'status' => 'completed',
            'actual_cost' => 425000,
        ]);
        $this->assertDatabaseHas('asset_registers', [
            'id' => $asset->id,
            'condition' => 'good',
            'status' => 'active',
        ]);

        $indexResponse = $this->actingAs($user)->get('/asset-maintenances');

        $indexResponse->assertOk();
        $indexResponse->assertSee('Asset Maintenance');
        $indexResponse->assertSee($maintenance->document_number);

        $printResponse = $this->actingAs($user)->get('/asset-maintenances/'.$maintenance->id.'/print');

        $printResponse->assertOk();
        $printResponse->assertSee('MAINTENANCE WORK ORDER');
        $printResponse->assertSee($maintenance->document_number);
        $printResponse->assertSee('Bali Tech Service');
    }

    public function test_purchasing_cycle_report_shows_pr_po_and_gr_progress(): void
    {
        $this->test_goods_receipt_can_be_created_and_posted_from_approved_purchase_order();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();

        $response = $this->actingAs($user)->get('/reports/purchasing/cycle?'.http_build_query([
            'date_from' => now()->subDay()->format('Y-m-d'),
            'date_to' => now()->addDay()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $response->assertSee('Purchasing Cycle Report');
        $response->assertSee('PR to PO to GR Tracking');
        $response->assertSee('GR-');
        $response->assertSee('PO-');
        $response->assertSee('completed');
        $response->assertSee('100,0%');
    }

    public function test_purchasing_cycle_print_page_can_be_rendered(): void
    {
        $this->test_goods_receipt_can_be_created_and_posted_from_approved_purchase_order();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();

        $response = $this->actingAs($user)->get('/reports/purchasing/cycle/print?'.http_build_query([
            'date_from' => now()->subDay()->format('Y-m-d'),
            'date_to' => now()->addDay()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $response->assertSee('PURCHASING CYCLE REPORT');
        $response->assertSee('PR-');
        $response->assertSee('PO-');
        $response->assertSee('GR-');
        $response->assertSee('100,0%');
    }

    public function test_supplier_performance_report_shows_supplier_scorecard(): void
    {
        $this->test_goods_receipt_can_be_created_and_posted_from_approved_purchase_order();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();

        $response = $this->actingAs($user)->get('/reports/purchasing/suppliers?'.http_build_query([
            'date_from' => now()->subDay()->format('Y-m-d'),
            'date_to' => now()->addDay()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $response->assertSee('Supplier Performance');
        $response->assertSee('Supplier Scorecard');
        $response->assertSee('Bali Fresh Market');
        $response->assertSee('100,0%');
        $response->assertSee('excellent');
    }

    public function test_supplier_performance_print_page_can_be_rendered(): void
    {
        $this->test_goods_receipt_can_be_created_and_posted_from_approved_purchase_order();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();

        $response = $this->actingAs($user)->get('/reports/purchasing/suppliers/print?'.http_build_query([
            'date_from' => now()->subDay()->format('Y-m-d'),
            'date_to' => now()->addDay()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $response->assertSee('SUPPLIER PERFORMANCE REPORT');
        $response->assertSee('Bali Fresh Market');
        $response->assertSee('100,0%');
        $response->assertSee('excellent');
    }

    public function test_stock_on_hand_page_shows_posted_stock_balance(): void
    {
        $this->test_goods_receipt_can_be_created_and_posted_from_approved_purchase_order();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();

        $response = $this->actingAs($user)->get('/inventory/stock-on-hand');

        $response->assertOk();
        $response->assertSee('Stock On Hand');
        $response->assertSee('ITM-RICE-01');
        $response->assertSee('MAIN-WH');
    }

    public function test_stock_opname_can_be_created_and_posted_as_stock_adjustment(): void
    {
        $this->test_goods_receipt_can_be_created_and_posted_from_approved_purchase_order();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $location = DB::table('storage_locations')->where('code', 'MAIN-WH')->firstOrFail();
        $item = DB::table('items')->where('sku', 'ITM-RICE-01')->firstOrFail();

        $createResponse = $this->actingAs($user)->get('/stock-opnames/create?storage_location_id='.$location->id);

        $createResponse->assertOk();
        $createResponse->assertSee('Buat Stock Opname');
        $createResponse->assertSee('ITM-RICE-01');

        $storeResponse = $this->actingAs($user)->post('/stock-opnames', [
            'storage_location_id' => $location->id,
            'count_date' => now()->format('Y-m-d'),
            'notes' => 'Opname test',
            'items' => [
                [
                    'item_id' => $item->id,
                    'counted_quantity' => 8,
                    'notes' => 'Selisih hitung fisik',
                ],
            ],
        ]);

        $stockOpname = DB::table('stock_opnames')
            ->where('notes', 'Opname test')
            ->firstOrFail();

        $storeResponse->assertRedirect('/stock-opnames/'.$stockOpname->id);
        $this->assertDatabaseHas('stock_opnames', [
            'id' => $stockOpname->id,
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('stock_opname_items', [
            'stock_opname_id' => $stockOpname->id,
            'item_id' => $item->id,
            'system_quantity' => 10,
            'counted_quantity' => 8,
            'variance_quantity' => -2,
        ]);

        $postResponse = $this->actingAs($user)->post('/stock-opnames/'.$stockOpname->id.'/post');

        $postResponse->assertRedirect('/stock-opnames/'.$stockOpname->id);
        $this->assertDatabaseHas('stock_opnames', [
            'id' => $stockOpname->id,
            'status' => 'posted',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'source_type' => 'stock_opname',
            'source_id' => $stockOpname->id,
            'item_id' => $item->id,
            'quantity' => -2,
            'movement_type' => 'stock_opname_adjustment',
        ]);

        $stockResponse = $this->actingAs($user)->get('/inventory/stock-on-hand');

        $stockResponse->assertOk();
        $stockResponse->assertSee('8,00');
    }

    public function test_stock_opname_print_page_can_be_rendered(): void
    {
        $this->test_stock_opname_can_be_created_and_posted_as_stock_adjustment();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $stockOpname = DB::table('stock_opnames')->firstOrFail();

        $response = $this->actingAs($user)->get('/stock-opnames/'.$stockOpname->id.'/print');

        $response->assertOk();
        $response->assertSee('STOCK OPNAME');
        $response->assertSee($stockOpname->document_number);
        $response->assertSee('MAIN-WH');
        $response->assertSee('ITM-RICE-01');
        $response->assertSee('Selisih Minus');
        $response->assertSee('Dihitung oleh');
        $response->assertSee('Diperiksa oleh');
        $response->assertSee('Disetujui oleh');
    }

    public function test_inventory_movement_report_shows_goods_receipt_and_stock_opname_adjustment(): void
    {
        $this->test_stock_opname_can_be_created_and_posted_as_stock_adjustment();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $location = DB::table('storage_locations')->where('code', 'MAIN-WH')->firstOrFail();
        $item = DB::table('items')->where('sku', 'ITM-RICE-01')->firstOrFail();

        $response = $this->actingAs($user)->get('/reports/inventory/movements?'.http_build_query([
            'date_from' => now()->subDay()->format('Y-m-d'),
            'date_to' => now()->addDay()->format('Y-m-d'),
            'storage_location_id' => $location->id,
            'item_id' => $item->id,
        ]));

        $response->assertOk();
        $response->assertSee('Laporan Mutasi Stok');
        $response->assertSee('GR-');
        $response->assertSee('SO-');
        $response->assertSee('goods receipt');
        $response->assertSee('stock opname adjustment');
        $response->assertSee('8,00');
    }
}
