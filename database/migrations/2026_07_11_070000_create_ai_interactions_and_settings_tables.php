<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_company_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('allow_external_provider')->default(false);
            $table->unsignedInteger('monthly_request_limit')->default(100);
            $table->unsignedBigInteger('monthly_token_limit')->default(100000);
            $table->timestamps();
        });

        Schema::create('ai_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->string('intent', 50)->nullable();
            $table->text('question')->nullable();
            $table->longText('answer');
            $table->string('provider', 30)->default('local');
            $table->string('model', 100)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_interactions');
        Schema::dropIfExists('ai_company_settings');
    }
};
