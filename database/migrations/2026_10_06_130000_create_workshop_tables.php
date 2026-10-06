<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('number', 80);
            $table->timestamp('purchased_at');
            $table->decimal('lines_amount', 14, 2);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('taxable_amount', 14, 2);
            $table->decimal('tax_percent', 8, 4)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('round_off', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'supplier_id']);
        });

        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name', 160);
            $table->string('item_code', 40);
            $table->decimal('net_weight', 12, 3);
            $table->decimal('rate_per_gram', 14, 2);
            $table->decimal('line_amount', 14, 2);
            $table->timestamps();

            $table->unique(['purchase_id', 'item_id']);
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('number', 80);
            $table->decimal('amount', 14, 2);
            $table->timestamp('returned_at');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
        });

        Schema::create('purchase_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_return_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->unique('purchase_line_id');
        });

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('number', 80);
            $table->decimal('amount', 14, 2);
            $table->decimal('refund_amount', 14, 2)->default(0);
            $table->timestamp('returned_at');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'sale_id']);
        });

        Schema::create('sale_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_return_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->unique('sale_line_id');
        });

        Schema::create('old_gold_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('metal_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('purity_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('number', 80);
            $table->timestamp('exchanged_at');
            $table->decimal('gross_weight', 12, 3);
            $table->decimal('stone_weight', 12, 3)->default(0);
            $table->decimal('net_weight', 12, 3);
            $table->decimal('melted_weight', 12, 3);
            $table->decimal('melting_loss_percent', 8, 4)->default(0);
            $table->decimal('rate_per_gram', 14, 2);
            $table->decimal('deduction_amount', 14, 2)->default(0);
            $table->decimal('exchange_value', 14, 2);
            $table->string('testing_result', 200)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'customer_id']);
        });

        Schema::create('repair_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('number', 80);
            $table->string('description', 160);
            $table->string('problem', 500);
            $table->string('technician', 80)->nullable();
            $table->decimal('estimated_cost', 14, 2)->default(0);
            $table->decimal('final_charge', 14, 2)->nullable();
            $table->date('expected_on')->nullable();
            $table->string('status', 20);
            $table->timestamp('received_at');
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('gold_schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('installment_mode', 20);
            $table->decimal('monthly_amount', 14, 2)->nullable();
            $table->unsignedSmallInteger('duration_months');
            $table->string('bonus_type', 30);
            $table->decimal('bonus_value', 14, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('scheme_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('gold_scheme_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('number', 80);
            $table->date('started_on');
            $table->string('status', 20);
            $table->timestamp('matured_at')->nullable();
            $table->decimal('maturity_amount', 14, 2)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'customer_id', 'status']);
        });

        Schema::create('scheme_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('scheme_enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->date('due_on');
            $table->decimal('amount', 14, 2);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['scheme_enrollment_id', 'paid_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('purchase_id')->nullable()->after('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_return_id')->nullable()->after('purchase_id')->constrained()->restrictOnDelete();
            $table->foreignId('old_gold_exchange_id')->nullable()->after('sale_return_id')->constrained()->restrictOnDelete();
            $table->foreignId('repair_order_id')->nullable()->after('old_gold_exchange_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('repair_order_id');
            $table->dropConstrainedForeignId('old_gold_exchange_id');
            $table->dropConstrainedForeignId('sale_return_id');
            $table->dropConstrainedForeignId('purchase_id');
        });

        Schema::dropIfExists('scheme_installments');
        Schema::dropIfExists('scheme_enrollments');
        Schema::dropIfExists('gold_schemes');
        Schema::dropIfExists('repair_orders');
        Schema::dropIfExists('old_gold_exchanges');
        Schema::dropIfExists('sale_return_lines');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('purchase_return_lines');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('purchase_lines');
        Schema::dropIfExists('purchases');
    }
};
