<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_modules', function (Blueprint $table): void {
            $table->boolean('is_licensed')->default(false)->after('module_id');
            $table->timestamp('licensed_until')->nullable()->after('is_enabled');
        });

        DB::table('company_modules')->where('is_enabled', true)->update(['is_licensed' => true]);

        Schema::create('company_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('plan_code', 60)->default('legacy');
            $table->string('license_model', 30)->default('perpetual');
            $table->string('billing_cycle', 30)->default('one_time');
            $table->string('status', 30)->default('active');
            $table->date('starts_on');
            $table->date('expires_on')->nullable();
            $table->date('grace_ends_on')->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_branches')->nullable();
            $table->unsignedBigInteger('storage_quota_bytes')->nullable();
            $table->string('license_reference', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('company_storage_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('mode', 30)->default('local');
            $table->string('provider', 40)->default('local');
            $table->string('status', 30)->default('active');
            $table->string('bucket')->nullable();
            $table->string('region', 100)->nullable();
            $table->string('endpoint', 500)->nullable();
            $table->string('root_prefix', 255)->nullable();
            $table->text('credentials_encrypted')->nullable();
            $table->unsignedBigInteger('quota_bytes')->nullable();
            $table->unsignedBigInteger('used_bytes')->default(0);
            $table->timestamp('last_tested_at')->nullable();
            $table->text('last_test_message')->nullable();
            $table->timestamps();
        });

        $now = now();
        foreach (DB::table('companies')->pluck('id') as $companyId) {
            DB::table('company_subscriptions')->insert([
                'company_id' => $companyId,
                'plan_code' => 'legacy',
                'license_model' => 'perpetual',
                'billing_cycle' => 'one_time',
                'status' => 'active',
                'starts_on' => today()->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('company_storage_profiles')->insert([
                'company_id' => $companyId,
                'mode' => 'local',
                'provider' => 'local',
                'status' => 'active',
                'root_prefix' => 'companies/'.$companyId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_storage_profiles');
        Schema::dropIfExists('company_subscriptions');

        Schema::table('company_modules', function (Blueprint $table): void {
            $table->dropColumn(['is_licensed', 'licensed_until']);
        });
    }
};
