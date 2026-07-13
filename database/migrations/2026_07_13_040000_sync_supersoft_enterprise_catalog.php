<?php

use App\Support\AccessControlProvisioner;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(AccessControlProvisioner::class)->syncAllCompanies();
    }

    public function down(): void
    {
        // Catalog entries are retained to protect existing entitlement history.
    }
};
