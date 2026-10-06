<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('girvi_pledge_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('girvi_pledge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('metal_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('purity_id')->constrained()->restrictOnDelete();
            $table->string('description', 160);
            $table->decimal('gross_weight', 12, 3);
            $table->decimal('stone_weight', 12, 3)->default(0);
            $table->decimal('net_weight', 12, 3);
            $table->decimal('rate_per_gram', 14, 2);
            $table->decimal('gold_value', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('girvi_pledge_items');
    }
};
