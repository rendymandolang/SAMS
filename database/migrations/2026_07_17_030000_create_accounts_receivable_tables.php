<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->text('address')->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('ar_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained('accounting_customers')->restrictOnDelete();
            $table->string('document_number', 60);
            $table->string('customer_reference', 100)->nullable();
            $table->date('invoice_date');
            $table->date('due_date');
            $table->string('currency', 3);
            $table->string('status', 30)->default('draft');
            $table->decimal('subtotal', 19, 4);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('total_amount', 19, 4);
            $table->decimal('received_amount', 19, 4)->default(0);
            $table->decimal('outstanding_amount', 19, 4);
            $table->foreignId('ar_account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->foreignId('tax_account_id')->nullable()->constrained('gl_accounts')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'document_number']);
            $table->index(['company_id', 'status', 'due_date']);
        });

        Schema::create('ar_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ar_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gl_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 19, 4)->default(1);
            $table->decimal('unit_price', 19, 4);
            $table->decimal('amount', 19, 4);
            $table->unsignedSmallInteger('line_number');
            $table->timestamps();
        });

        Schema::create('ar_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained('accounting_customers')->restrictOnDelete();
            $table->string('document_number', 60);
            $table->date('receipt_date');
            $table->string('currency', 3);
            $table->string('status', 30)->default('posted');
            $table->decimal('amount', 19, 4);
            $table->foreignId('cash_account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->foreignId('ar_account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->unique()->constrained()->restrictOnDelete();
            $table->string('receipt_reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at');
            $table->timestamps();
            $table->unique(['company_id', 'document_number']);
            $table->index(['company_id', 'customer_id', 'receipt_date']);
        });

        Schema::create('ar_receipt_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ar_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ar_invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 19, 4);
            $table->timestamps();
            $table->unique(['ar_receipt_id', 'ar_invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ar_receipt_allocations');
        Schema::dropIfExists('ar_receipts');
        Schema::dropIfExists('ar_invoice_lines');
        Schema::dropIfExists('ar_invoices');
        Schema::dropIfExists('accounting_customers');
    }
};
