<?php

use App\Support\AccessControlProvisioner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_consolidation_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('presentation_currency', 3);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('accounting_consolidation_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained('accounting_consolidation_groups')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->decimal('ownership_percent', 7, 4)->default(100);
            $table->boolean('is_parent')->default(false);
            $table->timestamps();
            $table->unique(['group_id', 'company_id'], 'consol_member_company_uq');
        });
        Schema::create('accounting_consolidation_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained('accounting_consolidation_groups')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('gl_account_id')->constrained()->restrictOnDelete();
            $table->string('consolidation_code', 60);
            $table->string('consolidation_name');
            $table->string('account_type', 30);
            $table->timestamps();
            $table->unique(['group_id', 'gl_account_id'], 'consol_mapping_account_uq');
        });
        Schema::create('accounting_consolidation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained('accounting_consolidation_groups')->restrictOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->string('status', 20)->default('draft');
            $table->decimal('total_debit', 19, 4)->default(0);
            $table->decimal('total_credit', 19, 4)->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->unique(['group_id', 'period_from', 'period_to'], 'consol_run_period_uq');
        });
        Schema::create('accounting_consolidation_run_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('accounting_consolidation_runs')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('source_currency', 3);
            $table->decimal('translation_rate', 19, 8)->default(1);
            $table->timestamps();
            $table->unique(['run_id', 'company_id'], 'consol_run_member_uq');
        });
        Schema::create('accounting_consolidation_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('accounting_consolidation_runs')->cascadeOnDelete();
            $table->foreignId('source_company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->string('consolidation_code', 60);
            $table->string('consolidation_name');
            $table->string('account_type', 30);
            $table->text('description')->nullable();
            $table->decimal('debit', 19, 4)->default(0);
            $table->decimal('credit', 19, 4)->default(0);
            $table->decimal('period_debit', 19, 4)->default(0);
            $table->decimal('period_credit', 19, 4)->default(0);
            $table->boolean('is_elimination')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['run_id', 'consolidation_code']);
        });
        app(AccessControlProvisioner::class)->syncAllCompanies();
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_consolidation_lines');
        Schema::dropIfExists('accounting_consolidation_run_members');
        Schema::dropIfExists('accounting_consolidation_runs');
        Schema::dropIfExists('accounting_consolidation_mappings');
        Schema::dropIfExists('accounting_consolidation_members');
        Schema::dropIfExists('accounting_consolidation_groups');
    }
};
