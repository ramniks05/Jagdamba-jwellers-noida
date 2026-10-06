<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('code', 20);
            $table->string('name', 160);
            $table->boolean('is_head_office')->default(false);
            $table->string('email', 160)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('gstin', 15)->nullable();
            $table->string('address_line1', 200)->nullable();
            $table->string('address_line2', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 12)->nullable();
            $table->string('country', 100)->default('India');
            $table->string('status', 20)->default('active');
            $table->string('timezone', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_head_office']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'name']);
            $table->index('gstin');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
