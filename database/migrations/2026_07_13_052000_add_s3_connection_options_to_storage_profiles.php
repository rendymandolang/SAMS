<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_storage_profiles', function (Blueprint $table): void {
            $table->boolean('use_path_style_endpoint')->default(true)->after('endpoint');
        });
    }

    public function down(): void
    {
        Schema::table('company_storage_profiles', function (Blueprint $table): void {
            $table->dropColumn('use_path_style_endpoint');
        });
    }
};
