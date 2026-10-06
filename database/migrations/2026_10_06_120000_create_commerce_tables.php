<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('stock_locations')->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('kind', 20);
            $table->string('code', 20);
            $table->string('name', 80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'branch_id', 'parent_id']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('code', 20);
            $table->string('name', 160);
            $table->string('mobile', 20)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('address_line1', 200)->nullable();
            $table->string('address_line2', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 12)->nullable();
            $table->string('country', 100)->default('India');
            $table->date('dob')->nullable();
            $table->date('anniversary')->nullable();
            $table->string('pan', 10)->nullable();
            $table->string('gstin', 15)->nullable();
            $table->string('id_proof_type', 40)->nullable();
            $table->string('id_proof_number', 40)->nullable();
            $table->string('kyc_status', 20)->default('pending');
            $table->string('customer_type', 20)->default('retail');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'mobile']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('code', 20);
            $table->string('name', 160);
            $table->string('contact_name', 160)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('address_line1', 200)->nullable();
            $table->string('address_line2', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 12)->nullable();
            $table->string('country', 100)->default('India');
            $table->string('pan', 10)->nullable();
            $table->string('gstin', 15)->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->string('account_number', 40)->nullable();
            $table->string('ifsc', 11)->nullable();
            $table->string('kyc_status', 20)->default('pending');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_location_id')->nullable()->constrained('stock_locations')->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('sku', 40);
            $table->string('item_code', 40);
            $table->string('barcode', 64)->nullable();
            $table->string('rfid', 64)->nullable();
            $table->string('name', 160);
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('collection_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('design_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('metal_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('purity_id')->constrained()->restrictOnDelete();
            $table->decimal('gross_weight', 12, 3);
            $table->decimal('stone_weight', 12, 3)->default(0);
            $table->decimal('other_weight', 12, 3)->default(0);
            $table->decimal('net_weight', 12, 3);
            $table->foreignId('making_method_id')->nullable()->constrained('charge_methods')->restrictOnDelete();
            $table->decimal('making_value', 14, 4)->default(0);
            $table->foreignId('wastage_method_id')->nullable()->constrained('charge_methods')->restrictOnDelete();
            $table->decimal('wastage_value', 14, 4)->default(0);
            $table->decimal('stone_value', 14, 2)->default(0);
            $table->decimal('cost_price', 14, 2)->default(0);
            $table->decimal('selling_price', 14, 2)->default(0);
            $table->decimal('mrp', 14, 2)->default(0);
            $table->string('certificate_number', 40)->nullable();
            $table->string('hallmark', 40)->nullable();
            $table->string('huid', 32)->nullable();
            $table->string('image_path')->nullable();
            $table->string('status', 20)->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'sku']);
            $table->unique(['company_id', 'item_code']);
            $table->unique(['company_id', 'barcode']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'metal_type_id', 'purity_id']);
            $table->index(['company_id', 'category_id']);
        });

        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('type', 30);
            $table->string('reference_type', 40)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->decimal('gross_weight', 12, 3)->default(0);
            $table->decimal('net_weight', 12, 3)->default(0);
            $table->decimal('rate', 14, 2)->nullable();
            $table->decimal('value', 14, 2)->nullable();
            $table->foreignId('source_location_id')->nullable()->constrained('stock_locations')->restrictOnDelete();
            $table->foreignId('destination_location_id')->nullable()->constrained('stock_locations')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['company_id', 'item_id', 'occurred_at']);
            $table->index(['company_id', 'type']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('metal_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('metal_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('purity_id')->constrained()->restrictOnDelete();
            $table->decimal('rate_per_gram', 14, 2);
            $table->timestamp('effective_at');
            $table->string('source', 40)->default('manual');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 200)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'metal_type_id', 'purity_id', 'effective_at']);
            $table->index(['company_id', 'branch_id', 'effective_at']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('party_type', 20);
            $table->unsignedBigInteger('party_id');
            $table->string('direction', 10);
            $table->decimal('amount', 14, 2);
            $table->string('narration', 200);
            $table->string('reference_type', 40)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('occurred_at');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'party_type', 'party_id', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('number', 80);
            $table->string('status', 20)->default('posted');
            $table->timestamp('sold_at');
            $table->decimal('lines_amount', 14, 2);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('taxable_amount', 14, 2);
            $table->decimal('tax_percent', 8, 4)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->boolean('prices_include_tax')->default(false);
            $table->decimal('round_off', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'sold_at']);
            $table->index(['company_id', 'customer_id']);
        });

        Schema::create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('metal_rate_id')->nullable()->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name', 160);
            $table->string('item_code', 40);
            $table->string('metal_name', 80);
            $table->string('purity_name', 80);
            $table->decimal('gross_weight', 12, 3);
            $table->decimal('stone_weight', 12, 3)->default(0);
            $table->decimal('other_weight', 12, 3)->default(0);
            $table->decimal('net_weight', 12, 3);
            $table->decimal('rate_per_gram', 14, 2);
            $table->string('making_method', 40)->nullable();
            $table->decimal('making_value', 14, 4)->default(0);
            $table->string('wastage_method', 40)->nullable();
            $table->decimal('wastage_value', 14, 4)->default(0);
            $table->decimal('metal_amount', 14, 2);
            $table->decimal('wastage_amount', 14, 2);
            $table->decimal('making_amount', 14, 2);
            $table->decimal('stone_amount', 14, 2);
            $table->decimal('line_amount', 14, 2);
            $table->timestamps();

            $table->index(['company_id', 'sale_id']);
            $table->unique(['sale_id', 'item_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('number', 80);
            $table->string('direction', 10);
            $table->string('method', 20);
            $table->decimal('amount', 14, 2);
            $table->string('reference', 80)->nullable();
            $table->string('narration', 200)->nullable();
            $table->timestamp('received_at');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'customer_id', 'received_at']);
            $table->index(['company_id', 'sale_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sale_lines');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('metal_rates');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('items');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('stock_locations');
    }
};
