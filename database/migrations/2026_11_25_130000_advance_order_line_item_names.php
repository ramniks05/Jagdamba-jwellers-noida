<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('advance_order_lines', 'item_name')) {
            return;
        }

        Schema::table('advance_order_lines', function (Blueprint $table) {
            $table->string('item_name', 160)->nullable()->after('advance_order_id');
        });

        // Orders booked before this change had one name for the whole order; give it to each of their lines.
        DB::table('advance_order_lines')->orderBy('id')->each(function ($line) {
            $name = DB::table('advance_orders')->where('id', $line->advance_order_id)->value('description');

            if ($name !== null) {
                DB::table('advance_order_lines')->where('id', $line->id)->update(['item_name' => $name]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('advance_order_lines', 'item_name')) {
            Schema::table('advance_order_lines', function (Blueprint $table) {
                $table->dropColumn('item_name');
            });
        }
    }
};
