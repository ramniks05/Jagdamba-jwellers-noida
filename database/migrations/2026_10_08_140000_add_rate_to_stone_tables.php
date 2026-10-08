<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['item_stones', 'sale_line_stones'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->decimal('rate', 14, 2)->nullable()->after('value');
                $table->string('rate_unit', 10)->nullable()->after('rate');
            });
        }
    }

    public function down(): void
    {
        foreach (['item_stones', 'sale_line_stones'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['rate', 'rate_unit']);
            });
        }
    }
};
