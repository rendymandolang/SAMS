<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_backups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('status', 30)->default('creating');
            $table->string('disk', 60);
            $table->string('path');
            $table->string('format', 30)->default('supersoft-json-v1');
            $table->string('encryption', 30)->default('aes-256-cbc');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->char('checksum_sha256', 64)->nullable();
            $table->unsignedInteger('table_count')->default(0);
            $table->unsignedBigInteger('row_count')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_status', 30)->nullable();
            $table->text('verification_message')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'created_at']);
        });

        Schema::create('accounting_document_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('period', 6);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['company_id', 'period']);
        });

        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->foreignId('reversal_of_id')->nullable()->unique()->after('source_type')->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('reversed_by_id')->nullable()->unique()->after('reversal_of_id')->constrained('journal_entries')->restrictOnDelete();
            $table->text('reversal_reason')->nullable()->after('is_adjustment');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reversed_by_id');
            $table->dropConstrainedForeignId('reversal_of_id');
            $table->dropColumn('reversal_reason');
        });

        Schema::dropIfExists('accounting_document_sequences');
        Schema::dropIfExists('company_backups');
    }
};
