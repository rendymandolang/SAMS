<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('provider_key', 80);
            $table->string('name');
            $table->string('category', 50);
            $table->string('status', 30)->default('not_tested');
            $table->boolean('is_active')->default(false);
            $table->string('credential_source', 30)->default('environment');
            $table->unsignedInteger('sync_interval_minutes')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->unsignedInteger('last_response_ms')->nullable();
            $table->string('last_message')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'provider_key']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_connections');
    }
};
