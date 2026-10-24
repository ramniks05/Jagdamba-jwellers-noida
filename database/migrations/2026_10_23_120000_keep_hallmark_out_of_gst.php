<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bills saved before this keep a null hallmark_amount: their Hallmark was inside GST and stays that way.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('hallmark_amount', 14, 2)->nullable()->after('making_tax_amount');
        });

        DB::table('settings')->where('key', 'pricing.making_mode')->whereNull('branch_id')->update(['value' => 'separate']);
        DB::table('settings')->where('key', 'pricing.making_gst_percent')->whereNull('branch_id')->update(['value' => '3']);
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('hallmark_amount');
        });
    }
};
