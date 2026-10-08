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
        Schema::create('advance_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('metal_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('purity_id')->constrained()->restrictOnDelete();
            $table->foreignId('metal_rate_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('number', 80);
            $table->string('description', 160);
            $table->text('design_notes')->nullable();
            $table->decimal('expected_weight', 12, 3);
            $table->decimal('rate_per_gram', 14, 2);
            $table->decimal('estimated_making', 14, 2)->default(0);
            $table->decimal('advance_paid', 14, 2)->default(0);
            $table->decimal('advance_refunded', 14, 2)->default(0);
            $table->string('status', 20);
            $table->timestamp('booked_at');
            $table->date('due_on')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('advance_order_id')->nullable()->after('girvi_pledge_id')->constrained('advance_orders')->restrictOnDelete();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('advance_amount', 14, 2)->default(0)->after('paid_amount');
        });

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            $exists = DB::table('document_sequences')
                ->where('company_id', $companyId)
                ->where('scope_key', 'company')
                ->where('document_type', 'advance_order')
                ->exists();

            if (! $exists) {
                DB::table('document_sequences')->insert([
                    'company_id' => $companyId,
                    'uuid' => (string) Str::uuid(),
                    'scope_key' => 'company',
                    'document_type' => 'advance_order',
                    'prefix' => 'ORD',
                    'separator' => config('foundation.document_sequence.separator', '-'),
                    'padding' => config('foundation.document_sequence.padding', 4),
                    'next_number' => 1,
                    'reset_policy' => 'financial_year',
                    'is_active' => true,
                    'is_system' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('document_sequences')->where('document_type', 'advance_order')->delete();

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('advance_amount');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('advance_order_id');
        });

        Schema::dropIfExists('advance_orders');
    }
};
