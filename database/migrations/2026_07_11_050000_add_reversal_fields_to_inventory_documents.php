<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['goods_receipts', 'stock_opnames'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->timestamp('reversed_at')->nullable()->after('posted_at');
                $table->foreignId('reversed_by')->nullable()->after('reversed_at')->constrained('users')->nullOnDelete();
                $table->text('reversal_reason')->nullable()->after('reversed_by');
            });
        }
    }

    public function down(): void
    {
        foreach (['goods_receipts', 'stock_opnames'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('reversed_by');
                $table->dropColumn(['reversed_at', 'reversal_reason']);
            });
        }
    }
};
