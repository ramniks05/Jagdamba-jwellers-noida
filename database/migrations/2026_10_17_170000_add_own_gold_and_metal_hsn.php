<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metal_types', function (Blueprint $table) {
            $table->string('hsn_code', 8)->nullable()->after('name');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->boolean('own_gold')->default(false)->after('source');
        });

        Schema::table('sale_lines', function (Blueprint $table) {
            $table->string('hsn_code', 8)->nullable()->after('purity_name');
        });

        DB::table('metal_types')->whereIn('code', ['GOLD', 'SILVER', 'PLATINUM'])->whereNull('hsn_code')->update([
            'hsn_code' => '7113',
        ]);
    }

    public function down(): void
    {
        Schema::table('sale_lines', function (Blueprint $table) {
            $table->dropColumn('hsn_code');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('own_gold');
        });

        Schema::table('metal_types', function (Blueprint $table) {
            $table->dropColumn('hsn_code');
        });
    }
};
