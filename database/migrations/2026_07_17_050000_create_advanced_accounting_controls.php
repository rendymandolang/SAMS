<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('po_price_tolerance_percent', 9, 4)->default(2);
            $table->decimal('po_quantity_tolerance_percent', 9, 4)->default(0);
            $table->timestamps();
        });
        Schema::create('accounting_tax_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('type', 20);
            $table->decimal('rate_percent', 9, 4);
            $table->foreignId('gl_account_id')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'type', 'is_active']);
        });
        Schema::create('accounting_posting_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_type', 40);
            $table->string('account_role', 40);
            $table->foreignId('gl_account_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'transaction_type', 'account_role'], 'accounting_posting_rule_unique');
        });
        Schema::table('ap_invoices', function (Blueprint $table): void {
            $table->foreignId('tax_code_id')->nullable()->after('tax_account_id')->constrained('accounting_tax_codes')->restrictOnDelete();
            $table->foreignId('withholding_tax_code_id')->nullable()->after('tax_code_id')->constrained('accounting_tax_codes')->restrictOnDelete();
            $table->decimal('withholding_amount', 19, 4)->default(0)->after('tax_amount');
            $table->decimal('gross_amount', 19, 4)->default(0)->after('withholding_amount');
            $table->decimal('credited_amount', 19, 4)->default(0)->after('paid_amount');
        });
        Schema::table('ar_invoices', function (Blueprint $table): void {
            $table->foreignId('tax_code_id')->nullable()->after('tax_account_id')->constrained('accounting_tax_codes')->restrictOnDelete();
            $table->decimal('credited_amount', 19, 4)->default(0)->after('received_amount');
        });
        Schema::table('ap_invoice_lines', function (Blueprint $table): void {
            $table->foreignId('purchase_order_item_id')->nullable()->after('ap_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_item_id')->nullable()->after('purchase_order_item_id')->constrained()->restrictOnDelete();
            $table->string('matching_status', 20)->nullable()->after('amount');
            $table->decimal('price_variance_percent', 9, 4)->nullable()->after('matching_status');
            $table->decimal('quantity_variance', 19, 4)->nullable()->after('price_variance_percent');
        });
        Schema::create('accounting_credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->foreignId('ap_invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('ar_invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('document_number', 60);
            $table->string('external_reference', 120)->nullable();
            $table->date('credit_date');
            $table->string('currency', 3);
            $table->decimal('amount', 19, 4);
            $table->foreignId('control_account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->foreignId('offset_account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->text('reason');
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'document_number']);
            $table->index(['company_id', 'type', 'status']);
        });
        Schema::table('ap_payments', function (Blueprint $table): void {
            $table->foreignId('reversal_journal_entry_id')->nullable()->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason', 500)->nullable();
        });
        Schema::table('ar_receipts', function (Blueprint $table): void {
            $table->foreignId('reversal_journal_entry_id')->nullable()->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason', 500)->nullable();
        });
        Schema::create('fiscal_year_closes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->date('closing_date');
            $table->foreignId('retained_earnings_account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->decimal('net_income', 19, 4);
            $table->string('status', 20)->default('completed');
            $table->foreignId('journal_entry_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('reversal_journal_entry_id')->nullable()->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('closed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at');
            $table->timestamp('reopened_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_year_closes');
        Schema::table('ar_receipts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reversed_by');
            $table->dropConstrainedForeignId('reversal_journal_entry_id');
            $table->dropColumn(['reversed_at', 'reversal_reason']);
        });
        Schema::table('ap_payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reversed_by');
            $table->dropConstrainedForeignId('reversal_journal_entry_id');
            $table->dropColumn(['reversed_at', 'reversal_reason']);
        });
        Schema::dropIfExists('accounting_credit_notes');
        Schema::table('ap_invoice_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('goods_receipt_item_id');
            $table->dropConstrainedForeignId('purchase_order_item_id');
            $table->dropColumn(['matching_status', 'price_variance_percent', 'quantity_variance']);
        });
        Schema::table('ar_invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tax_code_id');
            $table->dropColumn('credited_amount');
        });
        Schema::table('ap_invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('withholding_tax_code_id');
            $table->dropConstrainedForeignId('tax_code_id');
            $table->dropColumn(['withholding_amount', 'gross_amount', 'credited_amount']);
        });
        Schema::dropIfExists('accounting_posting_rules');
        Schema::dropIfExists('accounting_tax_codes');
        Schema::dropIfExists('accounting_settings');
    }
};
