<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('girvi_pledges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('metal_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('purity_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('number', 80);
            $table->string('description', 160);
            $table->decimal('gross_weight', 12, 3);
            $table->decimal('stone_weight', 12, 3)->default(0);
            $table->decimal('net_weight', 12, 3);
            $table->decimal('rate_per_gram', 14, 2);
            $table->decimal('gold_value', 14, 2);
            $table->string('loan_mode', 20);
            $table->decimal('loan_percent', 8, 4)->nullable();
            $table->decimal('principal', 14, 2);
            $table->decimal('interest_percent', 8, 4);
            $table->decimal('interest_charged', 14, 2)->default(0);
            $table->string('status', 20);
            $table->timestamp('pledged_at');
            $table->date('interest_from');
            $table->timestamp('released_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('girvi_pledge_id')->nullable()->after('repair_order_id')->constrained('girvi_pledges')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('girvi_pledge_id');
        });

        Schema::dropIfExists('girvi_pledges');
    }
};
