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

    public function test_goods_receipt_can_be_created_and_posted_from_approved_purchase_order(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $department = DB::table('departments')->where('code', 'PUR')->firstOrFail();
        $item = DB::table('items')->where('sku', 'ITM-RICE-01')->firstOrFail();
        $supplier = DB::table('suppliers')->where('code', 'SUP-FOOD-01')->firstOrFail();
        $location = DB::table('storage_locations')->where('code', 'MAIN-WH')->firstOrFail();

        $this->actingAs($user)->post('/purchase-requests', [
            'department_id' => $department->id,
            'request_date' => now()->format('Y-m-d'),
            'priority' => 'normal',
            'purpose' => 'GR flow',
            'lines' => [
                [
                    'item_id' => $item->id,
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
    }
}
