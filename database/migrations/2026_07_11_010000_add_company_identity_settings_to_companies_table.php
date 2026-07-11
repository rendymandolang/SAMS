<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->text('address')->nullable()->after('tax_number');
            $table->string('phone', 50)->nullable()->after('address');
            $table->string('email')->nullable()->after('phone');
            $table->string('logo_path')->nullable()->after('email');
            $table->string('locale', 5)->default('id')->after('logo_path');
            $table->string('date_format', 20)->default('d/m/Y')->after('locale');
            $table->string('time_format', 20)->default('H:i')->after('date_format');
            $table->string('primary_color', 7)->default('#5967D8')->after('currency');
            $table->string('sidebar_color', 7)->default('#182335')->after('primary_color');
            $table->string('accent_color', 7)->default('#2F9D8F')->after('sidebar_color');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'address',
                'phone',
                'email',
                'logo_path',
                'locale',
                'date_format',
                'time_format',
                'primary_color',
                'sidebar_color',
                'accent_color',
            ]);
        });
    }
};
