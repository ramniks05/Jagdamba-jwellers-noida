<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('metal_stock_movements')) {
            Schema::create('metal_stock_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->foreignId('branch_id')->constrained()->restrictOnDelete();
                $table->foreignId('metal_type_id')->constrained()->restrictOnDelete();
                $table->foreignId('purity_id')->constrained()->restrictOnDelete();
                $table->string('kind', 20);
                $table->decimal('grams', 12, 3);
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamp('occurred_at');
                $table->timestamps();

                $table->index(['company_id', 'branch_id', 'metal_type_id', 'purity_id'], 'metal_stock_lookup_idx');
                $table->index(['reference_type', 'reference_id'], 'metal_stock_reference_idx');
            });

            return;
        }

        $names = collect(Schema::getIndexes('metal_stock_movements'))->pluck('name');

        Schema::table('metal_stock_movements', function (Blueprint $table) use ($names) {
            if (! $names->contains('metal_stock_lookup_idx')) {
                $table->index(['company_id', 'branch_id', 'metal_type_id', 'purity_id'], 'metal_stock_lookup_idx');
            }

            if (! $names->contains('metal_stock_reference_idx')) {
                $table->index(['reference_type', 'reference_id'], 'metal_stock_reference_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metal_stock_movements');
    }
};
