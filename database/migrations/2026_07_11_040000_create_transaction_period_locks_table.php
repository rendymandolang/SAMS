<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_period_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('module', 40);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('reason');
            $table->foreignId('locked_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'module', 'starts_on', 'ends_on'], 'transaction_period_locks_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_period_locks');
    }
};
