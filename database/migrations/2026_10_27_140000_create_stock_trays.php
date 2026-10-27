<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_trays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('code', 20);
            $table->string('name', 80);
            $table->decimal('grams', 12, 3)->default(0);
            $table->unsignedInteger('pieces')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('tray_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_tray_id')->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->decimal('grams_delta', 12, 3);
            $table->integer('pieces_delta');
            $table->nullableMorphs('reference');
            $table->string('notes', 200)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['stock_tray_id', 'occurred_at']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('stock_tray_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_tray_id');
        });

        Schema::dropIfExists('tray_movements');
        Schema::dropIfExists('stock_trays');
    }
};
