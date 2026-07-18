<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3);
            $table->date('rate_date');
            $table->decimal('rate_to_base', 19, 8);
            $table->string('source', 80)->default('manual');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'currency', 'rate_date'], 'exchange_rate_unique');
        });
        Schema::table('accounting_settings', function (Blueprint $table): void {
            $table->foreignId('realized_fx_gain_account_id')->nullable()->constrained('gl_accounts')->restrictOnDelete();
            $table->foreignId('realized_fx_loss_account_id')->nullable()->constrained('gl_accounts')->restrictOnDelete();
            $table->foreignId('unrealized_fx_gain_account_id')->nullable()->constrained('gl_accounts')->restrictOnDelete();
            $table->foreignId('unrealized_fx_loss_account_id')->nullable()->constrained('gl_accounts')->restrictOnDelete();
        });
        Schema::table('journal_entry_lines', function (Blueprint $table): void {
            $table->string('foreign_currency', 3)->nullable()->after('description');
            $table->decimal('foreign_debit', 19, 4)->default(0)->after('foreign_currency');
            $table->decimal('foreign_credit', 19, 4)->default(0)->after('foreign_debit');
            $table->decimal('exchange_rate', 19, 8)->nullable()->after('foreign_credit');
        });
        foreach (['ap_invoices', 'ar_invoices'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->decimal('exchange_rate', 19, 8)->default(1)->after('currency');
                $table->decimal('foreign_subtotal', 19, 4)->default(0)->after('exchange_rate');
                $table->decimal('foreign_tax_amount', 19, 4)->default(0)->after('foreign_subtotal');
                $table->decimal('foreign_total_amount', 19, 4)->default(0)->after('foreign_tax_amount');
                $table->decimal('foreign_outstanding_amount', 19, 4)->default(0)->after('foreign_total_amount');
                $table->decimal('carrying_amount', 19, 4)->default(0)->after('foreign_outstanding_amount');
            });
        }
        Schema::table('ap_invoices', fn (Blueprint $table) => $table->decimal('foreign_withholding_amount', 19, 4)->default(0)->after('foreign_tax_amount'));
        foreach (['ap_invoice_lines', 'ar_invoice_lines'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->decimal('foreign_unit_price', 19, 4)->default(0)->after('unit_price');
                $table->decimal('foreign_amount', 19, 4)->default(0)->after('foreign_unit_price');
            });
        }
        foreach (['ap_payments', 'ar_receipts'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->decimal('exchange_rate', 19, 8)->default(1)->after('currency');
                $table->decimal('foreign_amount', 19, 4)->default(0)->after('exchange_rate');
                $table->decimal('realized_fx_amount', 19, 4)->default(0)->after('foreign_amount');
            });
        }
        Schema::table('ap_payment_allocations', fn (Blueprint $table) => $table->decimal('foreign_amount', 19, 4)->default(0)->after('amount'));
        Schema::table('ar_receipt_allocations', fn (Blueprint $table) => $table->decimal('foreign_amount', 19, 4)->default(0)->after('amount'));
        Schema::create('accounting_fx_revaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('revaluation_date');
            $table->string('currency', 3);
            $table->decimal('exchange_rate', 19, 8);
            $table->decimal('net_adjustment', 19, 4);
            $table->foreignId('journal_entry_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'revaluation_date', 'currency'], 'fx_revaluation_unique');
        });
        foreach (['ap_invoices', 'ar_invoices'] as $name) {
            DB::table($name)->update(['foreign_subtotal' => DB::raw('subtotal'), 'foreign_tax_amount' => DB::raw('tax_amount'), 'foreign_total_amount' => DB::raw('total_amount'), 'foreign_outstanding_amount' => DB::raw('outstanding_amount'), 'carrying_amount' => DB::raw('outstanding_amount')]);
        }
        DB::table('ap_invoices')->update(['foreign_withholding_amount' => DB::raw('withholding_amount')]);
        foreach (['ap_invoice_lines', 'ar_invoice_lines'] as $name) {
            DB::table($name)->update(['foreign_unit_price' => DB::raw('unit_price'), 'foreign_amount' => DB::raw('amount')]);
        }
        DB::table('ap_payments')->update(['foreign_amount' => DB::raw('amount')]);
        DB::table('ar_receipts')->update(['foreign_amount' => DB::raw('amount')]);
        DB::table('ap_payment_allocations')->update(['foreign_amount' => DB::raw('amount')]);
        DB::table('ar_receipt_allocations')->update(['foreign_amount' => DB::raw('amount')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_fx_revaluations');
        Schema::table('ar_receipt_allocations', fn (Blueprint $table) => $table->dropColumn('foreign_amount'));
        Schema::table('ap_payment_allocations', fn (Blueprint $table) => $table->dropColumn('foreign_amount'));
        foreach (['ar_receipts', 'ap_payments'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['exchange_rate', 'foreign_amount', 'realized_fx_amount']));
        }
        foreach (['ar_invoice_lines', 'ap_invoice_lines'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['foreign_unit_price', 'foreign_amount']));
        }
        Schema::table('ap_invoices', fn (Blueprint $table) => $table->dropColumn('foreign_withholding_amount'));
        foreach (['ar_invoices', 'ap_invoices'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['exchange_rate', 'foreign_subtotal', 'foreign_tax_amount', 'foreign_total_amount', 'foreign_outstanding_amount', 'carrying_amount']));
        }
        Schema::table('journal_entry_lines', fn (Blueprint $table) => $table->dropColumn(['foreign_currency', 'foreign_debit', 'foreign_credit', 'exchange_rate']));
        Schema::table('accounting_settings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('realized_fx_gain_account_id');
            $table->dropConstrainedForeignId('realized_fx_loss_account_id');
            $table->dropConstrainedForeignId('unrealized_fx_gain_account_id');
            $table->dropConstrainedForeignId('unrealized_fx_loss_account_id');
        });
        Schema::dropIfExists('accounting_exchange_rates');
    }
};
