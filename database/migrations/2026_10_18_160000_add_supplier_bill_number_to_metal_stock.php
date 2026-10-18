<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metal_stock_movements', function (Blueprint $table) {
            $table->string('supplier_bill_number', 40)->nullable()->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('metal_stock_movements', function (Blueprint $table) {
            $table->dropColumn('supplier_bill_number');
        });
    }
};
