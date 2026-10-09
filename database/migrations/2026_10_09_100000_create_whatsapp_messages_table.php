<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient', 20);
            $table->string('template', 80);
            $table->string('provider_id', 128)->nullable()->index();
            $table->string('status', 20);
            $table->string('error', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('status_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'sale_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
