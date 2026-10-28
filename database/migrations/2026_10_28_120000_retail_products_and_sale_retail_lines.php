<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retail_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('code', 20);
            $table->string('name', 160);
            $table->decimal('default_unit_price', 14, 2)->nullable();
            $table->string('default_tax_mode', 20)->default('none');
            $table->decimal('default_tax_percent', 8, 4)->nullable();
            $table->string('hsn_code', 20)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('sale_retail_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('retail_product_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name', 160);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 14, 2);
            $table->string('tax_mode', 20);
            $table->decimal('tax_percent', 8, 4)->nullable();
            $table->decimal('taxable_amount', 14, 2);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_amount', 14, 2);
            $table->string('hsn_code', 20)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'sale_id']);
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_retail_lines');
        Schema::dropIfExists('retail_products');
    }
};
