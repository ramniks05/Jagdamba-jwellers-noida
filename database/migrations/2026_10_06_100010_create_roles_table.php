<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('code', 50);
            $table->string('name', 80);
            $table->string('description', 255)->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'is_system']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
