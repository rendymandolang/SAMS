<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_credit_notes', function (Blueprint $table): void {
            $table->decimal('exchange_rate', 19, 8)->default(1)->after('currency');
            $table->decimal('foreign_amount', 19, 4)->default(0)->after('exchange_rate');
            $table->decimal('carrying_amount', 19, 4)->default(0)->after('foreign_amount');
            $table->decimal('realized_fx_amount', 19, 4)->default(0)->after('carrying_amount');
        });
        foreach (['ap_invoices', 'ar_invoices'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->decimal('foreign_credited_amount', 19, 4)->default(0)->after('credited_amount'));
        }
        DB::table('accounting_credit_notes')->update(['foreign_amount' => DB::raw('amount'), 'carrying_amount' => DB::raw('amount')]);
        foreach (['ap_invoices', 'ar_invoices'] as $name) {
            DB::table($name)->update(['foreign_credited_amount' => DB::raw('credited_amount')]);
        }
    }

    public function down(): void
    {
        foreach (['ar_invoices', 'ap_invoices'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('foreign_credited_amount'));
        }
        Schema::table('accounting_credit_notes', fn (Blueprint $table) => $table->dropColumn(['exchange_rate', 'foreign_amount', 'carrying_amount', 'realized_fx_amount']));
    }
};
