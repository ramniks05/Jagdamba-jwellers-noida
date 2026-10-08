<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('old_gold_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('metal_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('purity_id')->constrained()->restrictOnDelete();
            $table->string('direction', 3);
            $table->string('kind', 20);
            $table->decimal('gross_weight', 12, 3);
            $table->decimal('fine_weight', 12, 3);
            $table->decimal('value', 14, 2)->default(0);
            $table->decimal('amount_received', 14, 2)->nullable();
            $table->string('party', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('old_gold_exchange_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('moved_at');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'metal_type_id', 'purity_id']);
        });

        $now = now();

        foreach (DB::table('old_gold_exchanges')->orderBy('id')->get() as $exchange) {
            DB::table('old_gold_movements')->insert([
                'company_id' => $exchange->company_id,
                'branch_id' => $exchange->branch_id,
                'uuid' => (string) Str::uuid(),
                'metal_type_id' => $exchange->metal_type_id,
                'purity_id' => $exchange->purity_id,
                'direction' => 'in',
                'kind' => 'exchange',
                'gross_weight' => $exchange->gross_weight,
                'fine_weight' => $exchange->melted_weight,
                'value' => $exchange->exchange_value,
                'old_gold_exchange_id' => $exchange->id,
                'moved_at' => $exchange->exchanged_at,
                'user_id' => $exchange->user_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('old_gold_movements');
    }
};
