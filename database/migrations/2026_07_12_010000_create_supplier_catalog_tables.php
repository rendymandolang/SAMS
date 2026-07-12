<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_catalogs', function (Blueprint $table) {
            $table->id(); $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete(); $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('name'); $table->string('currency', 3)->default('IDR'); $table->date('valid_from')->nullable(); $table->date('valid_until')->nullable();
            $table->string('original_filename'); $table->string('stored_path'); $table->string('mime_type', 120); $table->string('status', 30)->default('uploaded');
            $table->unsignedInteger('row_count')->default(0); $table->json('scan_summary')->nullable(); $table->text('error_message')->nullable(); $table->timestamps();
            $table->index(['company_id', 'supplier_id', 'status']);
        });
        Schema::create('supplier_catalog_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('supplier_catalog_id')->constrained()->cascadeOnDelete(); $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_sku', 100)->nullable(); $table->string('source_name'); $table->string('brand')->nullable(); $table->string('category')->nullable(); $table->text('description')->nullable();
            $table->decimal('pack_quantity', 19, 4)->default(1); $table->string('pack_unit', 30)->default('PCS'); $table->decimal('price', 19, 4);
            $table->decimal('normalized_quantity', 19, 6)->default(1); $table->string('normalized_unit', 20)->default('PCS'); $table->decimal('normalized_unit_price', 19, 4);
            $table->decimal('minimum_order_quantity', 19, 4)->default(1); $table->string('stock_status', 30)->nullable(); $table->unsignedInteger('source_row')->nullable();
            $table->decimal('confidence', 5, 2)->default(0); $table->string('status', 30)->default('staged'); $table->json('raw_data')->nullable(); $table->timestamps();
            $table->index(['supplier_catalog_id', 'status'], 'catalog_items_status_idx'); $table->index(['normalized_unit', 'normalized_unit_price'], 'catalog_items_unit_price_idx');
        });
        Schema::create('supplier_comparison_runs', function (Blueprint $table) {
            $table->id(); $table->foreignId('company_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('query'); $table->decimal('quantity', 19, 4); $table->string('unit', 20); $table->decimal('budget', 19, 4)->nullable();
            $table->json('results'); $table->timestamps(); $table->index(['company_id', 'created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('supplier_comparison_runs'); Schema::dropIfExists('supplier_catalog_items'); Schema::dropIfExists('supplier_catalogs'); }
};
