<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('societies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('code', 20);
            $table->string('name', 80);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('society_id')->nullable()->after('country')->constrained()->nullOnDelete();
            $table->boolean('is_premium')->default(false)->after('society_id');

            $table->index(['company_id', 'society_id']);
            $table->index(['company_id', 'is_premium']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('society_id');
            $table->dropColumn('is_premium');
        });

        Schema::dropIfExists('societies');
    }
};
