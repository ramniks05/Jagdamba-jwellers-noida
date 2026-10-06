<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('code', 20);
            $table->string('name', 80);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'parent_id']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('brands', function (Blueprint $table) {
            $this->namedCatalog($table);
        });

        Schema::create('collections', function (Blueprint $table) {
            $this->namedCatalog($table);
        });

        Schema::create('designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('collection_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('design_number', 40);
            $table->string('name', 80);
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'design_number']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('metal_types', function (Blueprint $table) {
            $this->namedCatalog($table);
        });

        Schema::create('purities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('metal_type_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('code', 20);
            $table->string('name', 80);
            $table->decimal('fineness', 8, 6);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'metal_type_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('stone_types', function (Blueprint $table) {
            $this->namedCatalog($table);
        });

        Schema::create('stone_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('kind', 20);
            $table->string('code', 20);
            $table->string('name', 80);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'kind', 'code']);
            $table->index(['company_id', 'kind', 'is_active']);
        });

        Schema::create('charge_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('applies_to', 20);
            $table->string('code', 20);
            $table->string('name', 80);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'applies_to', 'code']);
            $table->index(['company_id', 'applies_to', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_methods');
        Schema::dropIfExists('stone_grades');
        Schema::dropIfExists('stone_types');
        Schema::dropIfExists('purities');
        Schema::dropIfExists('metal_types');
        Schema::dropIfExists('designs');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }

    private function namedCatalog(Blueprint $table): void
    {
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
    }
};
