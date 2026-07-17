<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gl_account_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('bank_name');
            $table->string('account_number_masked', 50)->nullable();
            $table->string('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->unique(['company_id', 'gl_account_id']);
        });

        Schema::create('bank_statement_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained('accounting_bank_accounts')->cascadeOnDelete();
            $table->string('original_filename');
            $table->char('file_hash', 64);
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('closing_balance', 19, 4);
            $table->unsignedInteger('line_count')->default(0);
            $table->string('status', 30)->default('imported');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['bank_account_id', 'file_hash'], 'bank_statement_file_unique');
            $table->index(['company_id', 'period_end']);
        });

        Schema::create('bank_statement_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_statement_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained('accounting_bank_accounts')->cascadeOnDelete();
            $table->date('transaction_date');
            $table->date('value_date')->nullable();
            $table->string('reference', 150)->nullable();
            $table->text('description');
            $table->decimal('amount', 19, 4);
            $table->decimal('running_balance', 19, 4)->nullable();
            $table->string('status', 30)->default('unmatched');
            $table->foreignId('matched_journal_entry_line_id')->nullable()->unique()->constrained('journal_entry_lines')->restrictOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_note', 500)->nullable();
            $table->timestamps();
            $table->index(['bank_account_id', 'transaction_date', 'status'], 'bank_statement_match_index');
        });

        Schema::create('bank_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained('accounting_bank_accounts')->cascadeOnDelete();
            $table->foreignId('bank_statement_import_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('statement_date');
            $table->decimal('statement_balance', 19, 4);
            $table->decimal('book_balance', 19, 4)->default(0);
            $table->decimal('difference', 19, 4)->default(0);
            $table->string('status', 20)->default('draft');
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'bank_account_id', 'statement_date'], 'bank_reconciliation_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statement_imports');
        Schema::dropIfExists('accounting_bank_accounts');
    }
};
