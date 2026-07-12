<?php
use App\Support\AccessControlProvisioner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{
  Schema::create('gl_accounts',function(Blueprint $t){$t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->foreignId('parent_id')->nullable()->constrained('gl_accounts')->nullOnDelete();$t->string('code',40);$t->string('name');$t->string('type',30);$t->string('normal_balance',6);$t->boolean('allow_posting')->default(true);$t->boolean('is_active')->default(true);$t->timestamps();$t->unique(['company_id','code']);$t->index(['company_id','type']);});
  Schema::create('journal_entries',function(Blueprint $t){$t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();$t->string('document_number',60);$t->date('journal_date');$t->string('source_type',40)->default('manual');$t->string('status',20)->default('draft');$t->boolean('is_adjustment')->default(false);$t->text('memo');$t->decimal('total_debit',19,4)->default(0);$t->decimal('total_credit',19,4)->default(0);$t->foreignId('created_by')->constrained('users')->restrictOnDelete();$t->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamp('posted_at')->nullable();$t->timestamps();$t->unique(['company_id','document_number']);$t->index(['company_id','journal_date','status']);});
  Schema::create('journal_entry_lines',function(Blueprint $t){$t->id();$t->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();$t->foreignId('gl_account_id')->constrained()->restrictOnDelete();$t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();$t->text('description')->nullable();$t->decimal('debit',19,4)->default(0);$t->decimal('credit',19,4)->default(0);$t->unsignedSmallInteger('line_number');$t->timestamps();$t->index(['gl_account_id','journal_entry_id']);});
  app(AccessControlProvisioner::class)->syncAllCompanies();
 }
 public function down():void{Schema::dropIfExists('journal_entry_lines');Schema::dropIfExists('journal_entries');Schema::dropIfExists('gl_accounts');}
};
