<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('financial_year_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('scope_key', 40);
            $table->string('document_type', 50);
            $table->string('prefix', 20);
            $table->string('suffix', 20)->nullable();
            $table->string('separator', 3)->default('-');
            $table->unsignedTinyInteger('padding')->default(4);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->string('reset_policy', 30);
            $table->string('last_period_key', 40)->nullable();
            $table->string('last_issued_number', 80)->nullable();
            $table->timestamp('last_issued_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'scope_key', 'document_type']);
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
