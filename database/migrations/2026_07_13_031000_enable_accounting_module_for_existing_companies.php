<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $moduleId = DB::table('modules')->where('key', 'accounting')->value('id');

        if ($moduleId) {
            DB::table('company_modules')
                ->where('module_id', $moduleId)
                ->update(['is_enabled' => true, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Accounting activation is intentionally not reversed automatically.
    }
};
