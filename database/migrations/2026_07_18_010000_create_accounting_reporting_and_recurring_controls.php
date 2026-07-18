<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_accounts', function (Blueprint $table): void {
            $table->boolean('is_cash_account')->default(false)->after('normal_balance');
            $table->string('cash_flow_activity', 20)->nullable()->after('is_cash_account');
        });
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->string('cash_flow_activity', 20)->nullable()->after('is_adjustment');
        });
        Schema::create('accounting_recurring_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('frequency', 20);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('next_run_on');
            $table->text('memo');
            $table->boolean('is_adjustment')->default(false);
            $table->string('cash_flow_activity', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'is_active', 'next_run_on'], 'recurring_template_due');
        });
        Schema::create('accounting_recurring_template_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('accounting_recurring_templates')->cascadeOnDelete();
            $table->foreignId('gl_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->decimal('debit', 19, 4)->default(0);
            $table->decimal('credit', 19, 4)->default(0);
            $table->unsignedSmallInteger('line_number');
            $table->timestamps();
        });
        Schema::create('accounting_recurring_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('accounting_recurring_templates')->restrictOnDelete();
            $table->date('scheduled_for');
            $table->foreignId('journal_entry_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['template_id', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_recurring_runs');
        Schema::dropIfExists('accounting_recurring_template_lines');
        Schema::dropIfExists('accounting_recurring_templates');
        Schema::table('journal_entries', fn (Blueprint $table) => $table->dropColumn('cash_flow_activity'));
        Schema::table('gl_accounts', fn (Blueprint $table) => $table->dropColumn(['is_cash_account', 'cash_flow_activity']));
    }
};
