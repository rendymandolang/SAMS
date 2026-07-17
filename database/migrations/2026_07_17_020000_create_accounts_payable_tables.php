<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_subledger_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 30);
            $table->string('period', 6);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['company_id', 'document_type', 'period'], 'acct_subledger_sequence_unique');
        });

        Schema::create('ap_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_number', 60);
            $table->string('supplier_invoice_number', 100);
            $table->date('invoice_date');
            $table->date('due_date');
            $table->string('currency', 3);
            $table->string('status', 30)->default('draft');
            $table->decimal('subtotal', 19, 4);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('total_amount', 19, 4);
            $table->decimal('paid_amount', 19, 4)->default(0);
            $table->decimal('outstanding_amount', 19, 4);
            $table->foreignId('ap_account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->foreignId('tax_account_id')->nullable()->constrained('gl_accounts')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'document_number']);
            $table->unique(['company_id', 'supplier_id', 'supplier_invoice_number'], 'ap_invoice_supplier_ref_unique');
            $table->index(['company_id', 'status', 'due_date']);
        });

        Schema::create('ap_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ap_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gl_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 19, 4)->default(1);
            $table->decimal('unit_price', 19, 4);
            $table->decimal('amount', 19, 4);
            $table->unsignedSmallInteger('line_number');
            $table->timestamps();
        });

        Schema::create('ap_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('document_number', 60);
            $table->date('payment_date');
            $table->string('currency', 3);
            $table->string('status', 30)->default('posted');
            $table->decimal('amount', 19, 4);
            $table->foreignId('cash_account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->foreignId('ap_account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->unique()->constrained()->restrictOnDelete();
            $table->string('payment_reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at');
            $table->timestamps();
            $table->unique(['company_id', 'document_number']);
            $table->index(['company_id', 'supplier_id', 'payment_date']);
        });

        Schema::create('ap_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ap_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ap_invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 19, 4);
            $table->timestamps();
            $table->unique(['ap_payment_id', 'ap_invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ap_payment_allocations');
        Schema::dropIfExists('ap_payments');
        Schema::dropIfExists('ap_invoice_lines');
        Schema::dropIfExists('ap_invoices');
        Schema::dropIfExists('accounting_subledger_sequences');
    }
};
