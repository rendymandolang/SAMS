<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('source_line_id')->nullable()->after('source_id');
            $table->unique(
                ['source_type', 'source_id', 'source_line_id', 'movement_type'],
                'stock_movements_source_line_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropUnique('stock_movements_source_line_unique');
            $table->dropColumn('source_line_id');
        });
    }
};
