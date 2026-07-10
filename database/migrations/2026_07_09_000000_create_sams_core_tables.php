<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->string('timezone', 50)->default('Asia/Makassar');
            $table->string('currency', 3)->default('IDR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('code', 30);
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('timezone', 50)->default('Asia/Makassar');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'branch_id', 'code']);
        });

        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('prefix', 30);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedTinyInteger('padding')->default(5);
            $table->string('period_format', 20)->default('Ym');
            $table->timestamps();
            $table->unique(['company_id', 'branch_id', 'document_type']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('code', 30);
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('item_categories')->nullOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 80);
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('storage_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('type', 30)->default('warehouse');
            $table->boolean('allow_negative_stock')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['branch_id', 'code']);
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('base_unit_id')->constrained('units')->restrictOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('sku', 50);
            $table->string('barcode', 80)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('item_type', 30)->default('inventory');
            $table->decimal('minimum_stock', 19, 4)->default(0);
            $table->decimal('maximum_stock', 19, 4)->nullable();
            $table->decimal('standard_cost', 19, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'sku']);
        });

        Schema::create('item_unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('conversion_factor', 19, 6);
            $table->boolean('is_purchase_unit')->default(false);
            $table->timestamps();
            $table->unique(['item_id', 'unit_id']);
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 30)->default('draft');
            $table->decimal('total_amount', 19, 4)->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->string('account_code', 50);
            $table->string('description');
            $table->decimal('allocated_amount', 19, 4);
            $table->decimal('committed_amount', 19, 4)->default(0);
            $table->decimal('actual_amount', 19, 4)->default(0);
            $table->timestamps();
            $table->unique(['budget_id', 'account_code']);
        });

        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('document_number', 60);
            $table->date('request_date');
            $table->date('required_date')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->string('status', 30)->default('draft');
            $table->text('purpose')->nullable();
            $table->decimal('estimated_total', 19, 4)->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'document_number']);
        });

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('budget_line_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 19, 4);
            $table->decimal('estimated_unit_price', 19, 4)->default(0);
            $table->decimal('estimated_total', 19, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('document_number', 60);
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('currency', 3)->default('IDR');
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('total_amount', 19, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'document_number']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_request_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 19, 4);
            $table->decimal('received_quantity', 19, 4)->default(0);
            $table->decimal('unit_price', 19, 4);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('line_total', 19, 4);
            $table->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->string('document_number', 60);
            $table->dateTime('received_at');
            $table->string('supplier_delivery_number', 80)->nullable();
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'document_number']);
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 19, 4);
            $table->decimal('accepted_quantity', 19, 4);
            $table->decimal('rejected_quantity', 19, 4)->default(0);
            $table->decimal('unit_cost', 19, 4);
            $table->date('expiry_date')->nullable();
            $table->string('lot_number', 80)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->string('movement_type', 30);
            $table->dateTime('movement_at');
            $table->decimal('quantity', 19, 4);
            $table->decimal('unit_cost', 19, 4)->default(0);
            $table->decimal('total_cost', 19, 4)->default(0);
            $table->nullableMorphs('source');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['storage_location_id', 'item_id', 'movement_at']);
        });

        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('document_number', 60);
            $table->date('count_date');
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'document_number']);
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('system_quantity', 19, 4);
            $table->decimal('counted_quantity', 19, 4)->nullable();
            $table->decimal('variance_quantity', 19, 4)->default(0);
            $table->decimal('unit_cost', 19, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['stock_opname_id', 'item_id']);
        });

        Schema::create('approval_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('document_type', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('approval_flow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_flow_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('approver_type', 30);
            $table->string('approver_value');
            $table->decimal('minimum_amount', 19, 4)->nullable();
            $table->decimal('maximum_amount', 19, 4)->nullable();
            $table->timestamps();
            $table->unique(['approval_flow_id', 'step_order']);
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_flow_id')->constrained()->restrictOnDelete();
            $table->morphs('approvable');
            $table->unsignedSmallInteger('current_step')->default(1);
            $table->string('status', 30)->default('pending');
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->foreignId('acted_by')->constrained('users')->restrictOnDelete();
            $table->string('action', 30);
            $table->text('comments')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->morphs('attachable');
            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 50);
            $table->nullableMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->uuid('correlation_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'event', 'created_at']);
        });
    }

    public function down(): void
    {
        $tables = [
            'audit_logs', 'attachments', 'approval_actions', 'approval_requests',
            'approval_flow_steps', 'approval_flows', 'stock_opname_items',
            'stock_opnames', 'stock_movements', 'goods_receipt_items',
            'goods_receipts', 'purchase_order_items', 'purchase_orders',
            'purchase_request_items', 'purchase_requests', 'budget_lines',
            'budgets', 'item_unit_conversions', 'items', 'storage_locations',
            'units', 'item_categories', 'suppliers', 'document_sequences',
            'company_user', 'departments', 'branches', 'companies',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
