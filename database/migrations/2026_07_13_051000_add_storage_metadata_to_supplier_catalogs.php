<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_catalogs', function (Blueprint $table): void {
            $table->string('disk', 60)->default('local')->after('original_filename');
            $table->unsignedBigInteger('file_size')->default(0)->after('mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_catalogs', function (Blueprint $table): void {
            $table->dropColumn(['disk', 'file_size']);
        });
    }
};
