<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modules') || ! Schema::hasTable('company_modules')) {
            return;
        }

        $moduleId = DB::table('modules')->where('key', 'intelligence')->value('id');
        if ($moduleId) {
            DB::table('company_modules')->where('module_id', $moduleId)->update(['is_enabled' => true, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $moduleId = Schema::hasTable('modules') ? DB::table('modules')->where('key', 'intelligence')->value('id') : null;
        if ($moduleId && Schema::hasTable('company_modules')) {
            DB::table('company_modules')->where('module_id', $moduleId)->update(['is_enabled' => false, 'updated_at' => now()]);
        }
    }
};
