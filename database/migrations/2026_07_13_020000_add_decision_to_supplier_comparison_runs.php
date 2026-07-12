<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_comparison_runs', function (Blueprint $table) {
            $table->string('status', 30)->default('analyzed')->after('budget');
            $table->json('summary')->nullable()->after('results');
            $table->foreignId('selected_supplier_id')->nullable()->after('summary')->constrained('suppliers')->nullOnDelete();
            $table->foreignId('selected_catalog_item_id')->nullable()->after('selected_supplier_id')->constrained('supplier_catalog_items')->nullOnDelete();
            $table->text('decision_reason')->nullable()->after('selected_catalog_item_id');
            $table->foreignId('decided_by')->nullable()->after('decision_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable()->after('decided_by');
            $table->index(['company_id', 'status', 'created_at'], 'supplier_comparison_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_comparison_runs', function (Blueprint $table) {
            $table->dropIndex('supplier_comparison_status_idx');
            $table->dropConstrainedForeignId('selected_supplier_id');
            $table->dropConstrainedForeignId('selected_catalog_item_id');
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn(['status', 'summary', 'decision_reason', 'decided_at']);
        });
    }
};
