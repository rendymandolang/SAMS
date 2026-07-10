<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('storage_location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('asset_number', 80);
            $table->string('asset_name');
            $table->string('serial_number', 120)->nullable();
            $table->date('acquisition_date');
            $table->decimal('acquisition_cost', 19, 4)->default(0);
            $table->string('condition', 30)->default('good');
            $table->string('status', 30)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'asset_number']);
            $table->index(['company_id', 'status', 'condition']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_registers');
    }
};
