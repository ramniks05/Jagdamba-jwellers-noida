<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metal_stock_movements', function (Blueprint $table) {
            $table->decimal('rate_per_gram', 14, 2)->nullable()->after('grams');
            $table->decimal('taxable_amount', 14, 2)->nullable()->after('rate_per_gram');
            $table->decimal('tax_percent', 5, 2)->nullable()->after('taxable_amount');
            $table->decimal('tax_amount', 14, 2)->nullable()->after('tax_percent');
            $table->decimal('total_amount', 14, 2)->nullable()->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('metal_stock_movements', function (Blueprint $table) {
            $table->dropColumn(['rate_per_gram', 'taxable_amount', 'tax_percent', 'tax_amount', 'total_amount']);
        });
    }
};
