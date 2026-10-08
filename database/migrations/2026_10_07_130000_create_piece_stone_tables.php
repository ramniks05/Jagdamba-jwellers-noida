<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_stones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->decimal('weight', 12, 3)->default(0);
            $table->decimal('value', 14, 2)->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['item_id', 'position'], 'item_stones_item_pos_idx');
        });

        Schema::create('sale_line_stones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_line_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->decimal('weight', 12, 3)->default(0);
            $table->decimal('value', 14, 2)->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['sale_line_id', 'position'], 'sale_line_stones_line_pos_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_line_stones');
        Schema::dropIfExists('item_stones');
    }
};
