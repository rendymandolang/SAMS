<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_number', 80);
            $table->string('maintenance_type', 30)->default('corrective');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 30)->default('open');
            $table->date('request_date');
            $table->date('scheduled_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->string('vendor_name')->nullable();
            $table->decimal('estimated_cost', 19, 4)->default(0);
            $table->decimal('actual_cost', 19, 4)->default(0);
            $table->text('issue_description');
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'document_number']);
            $table->index(['company_id', 'status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_maintenances');
    }
};
