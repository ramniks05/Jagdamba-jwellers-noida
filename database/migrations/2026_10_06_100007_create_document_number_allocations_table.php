<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_number_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_sequence_id')->constrained()->restrictOnDelete();
            $table->string('document_type', 50);
            $table->string('number', 80);
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'document_type', 'issued_at']);
            $table->index('document_sequence_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_number_allocations');
    }
};
