<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advance_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('advance_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('metal_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('purity_id')->constrained()->restrictOnDelete();
            $table->foreignId('metal_rate_id')->constrained()->restrictOnDelete();
            $table->decimal('expected_weight', 12, 3);
            $table->decimal('rate_per_gram', 14, 2);
            $table->decimal('estimated_making', 14, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['advance_order_id', 'sort_order']);
        });

        if (! Schema::hasTable('advance_orders')) {
            return;
        }

        $now = now();
        foreach (DB::table('advance_orders')->orderBy('id')->cursor() as $order) {
            DB::table('advance_order_lines')->insert([
                'company_id' => $order->company_id,
                'advance_order_id' => $order->id,
                'metal_type_id' => $order->metal_type_id,
                'purity_id' => $order->purity_id,
                'metal_rate_id' => $order->metal_rate_id,
                'expected_weight' => $order->expected_weight,
                'rate_per_gram' => $order->rate_per_gram,
                'estimated_making' => $order->estimated_making,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_order_lines');
    }
};
