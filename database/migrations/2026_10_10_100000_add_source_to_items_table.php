<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('source', 20)->default('own')->after('status');
            $table->index(['company_id', 'source']);
        });

        DB::table('items')
            ->whereIn('id', DB::table('purchase_lines')->select('item_id'))
            ->update(['source' => 'purchased']);

        DB::table('items')
            ->whereIn('id', DB::table('old_gold_movements')->whereNotNull('item_id')->select('item_id'))
            ->update(['source' => 'old_gold']);
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'source']);
            $table->dropColumn('source');
        });
    }
};
